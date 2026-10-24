<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Enums\PublicationType;
use App\Models\IdentityVerification;
use App\Models\PersonConnection;
use App\Models\Publication;
use App\Models\TrustReport;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WorkAttestation;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Attestations limitées à leur objet. Aucun document d’identité ni conversation créée par ce service. */
class PersonalTrust
{
    public function identity(User $user): ?IdentityVerification
    {
        return IdentityVerification::query()->current()->where('user_id', $user->id)->first();
    }

    public function requestIdentity(User $actor, bool $consent): void
    {
        abort_unless($actor->fresh()->isActive(), 403);
        $this->require($consent, 'Acceptez le contrôle humain et l’affichage de la mention avant de demander.');
        DB::transaction(function () use ($actor) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $row = IdentityVerification::where('user_id', $actor->id)->lockForUpdate()->first();
            $this->require($row?->state !== 'pending' && ! $this->identity($actor), 'Une demande est en cours ou votre identité est déjà vérifiée.');
            $this->require(! $row || $row->state === 'verified' || $row->updated_at->lte(now()->subDays(7)), 'Attendez sept jours avant une nouvelle demande, ou déposez un recours.');
            IdentityVerification::updateOrCreate(['user_id' => $actor->id], ['state' => 'pending', 'revision' => ($row?->revision ?? 0) + 1, 'checked_name' => $actor->name, 'verified_at' => null, 'expires_at' => null, 'method' => null, 'reviewer_id' => null, 'reason' => null, 'instruction' => null]);
            $this->reviewAlert();
        }, 3);
    }

    public function withdrawIdentity(User $actor, int $revision): void
    {
        DB::transaction(function () use ($actor, $revision) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $row = IdentityVerification::where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('view', $row);
            $this->version($row, $revision);
            $this->require(in_array($row->state, ['pending', 'verified'], true), 'Cette demande n’est plus active.');
            $row->update(['state' => 'withdrawn', 'revision' => $row->revision + 1, 'reason' => 'Consentement retiré par la personne.']);
        }, 3);
    }

    public function reviewIdentity(User $actor, string $id, int $revision, string $decision, string $reason = '', string $method = '', bool $nameChecked = false, bool $personChecked = false): void
    {
        abort_unless($actor->fresh()->isActive() && $actor->fresh()->isSuperAdmin(), 403);
        $initial = IdentityVerification::findOrFail($id);
        DB::transaction(function () use ($actor, $initial, $revision, $decision, $reason, $method, $nameChecked, $personChecked) {
            $person = User::whereKey($initial->user_id)->lockForUpdate()->firstOrFail();
            $row = IdentityVerification::whereKey($initial->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('review', $row);
            $this->version($row, $revision);
            abort_unless(in_array($decision, ['verify', 'decline', 'revoke', 'instruct', 'reopen'], true), 422);
            $this->require($decision === 'reopen' ? in_array($row->state, ['declined', 'revoked'], true) : $row->state === ($decision === 'revoke' ? 'verified' : 'pending'), 'Le dossier a changé. Rechargez la page.');
            $values = ['revision' => $row->revision + 1, 'reviewer_id' => $actor->id];
            if ($decision === 'reopen') {
                $this->require(TrustReport::where('subject_id', $person->id)->where('reporter_id', $person->id)->whereNull('work_attestation_id')->where('category', 'appeal')->where('state', 'open')->exists(), 'Un recours de la personne est nécessaire pour réexaminer ce dossier.');
                $values += ['state' => 'pending', 'checked_name' => $person->name, 'reason' => $this->text($reason, 500), 'verified_at' => null, 'expires_at' => null, 'method' => null];
            } elseif ($decision === 'verify') {
                $this->require($person->isActive() && $person->name === $row->checked_name, 'Le nom ou le compte a changé : un nouveau contrôle est nécessaire.');
                $this->require($nameChecked && $personChecked && in_array($method, ['in_person', 'live_review'], true), 'Effectuez les deux contrôles et choisissez leur modalité.');
                $values += ['state' => 'verified', 'method' => $method, 'verified_at' => now(), 'expires_at' => now()->addYear(), 'reason' => null];
            } elseif ($decision === 'instruct') {
                $values['instruction'] = $this->text($reason, 500);
            } else {
                $values += ['state' => $decision === 'decline' ? 'declined' : 'revoked', 'reason' => $this->text($reason, 500)];
            }
            $row->update($values);
            AdminJournal::record($actor, 'trust.identity_'.$decision, $person, $decision === 'verify' ? null : $reason, ['revision' => $row->revision]);
            $this->notify($person);
        }, 3);
    }

    public function candidates(User $author)
    {
        return User::query()->active()->whereKeyNot($author->id)
            ->whereHas('identityVerification', fn ($q) => $q->current())
            ->whereHas('profile', fn ($q) => $q->visibleTo($author))
            ->whereIn('id', PersonConnection::forPerson($author)->where('state', 'accepted')->get()->map(fn ($pair) => $pair->otherId($author)))
            ->orderBy('name_search')->limit(100)->get();
    }

    public function requestWork(User $actor, Publication $post, string $attesterId, string $claim, string $date): void
    {
        $data = Validator::make(['attester' => $attesterId, 'claim' => $claim, 'date' => $date], ['attester' => 'required|uuid', 'claim' => 'required|string|max:280', 'date' => 'required|date_format:Y-m-d|before_or_equal:today'])->validate();
        $claim = $this->text($data['claim'], 280);
        $other = User::findOrFail($attesterId);
        $this->lockedPeople($actor, $other, function (User $actor, User $other) use ($post, $claim, $date) {
            $post = Publication::whereKey($post->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manage', $post);
            $this->require($post->author_id === $actor->id && ! $post->zumra_id && $post->type === PublicationType::PersonalWork && ! $post->isHidden(), 'Choisissez une réalisation personnelle encore visible.');
            $this->require($actor->isActive() && $other->isActive() && ! People::blocked($actor, $other, fresh: true), 'Ce contact n’est pas disponible.');
            Gate::forUser($other)->authorize('view', $post);
            $this->require((bool) $this->identity($other) && app(People::class)->pair($actor, $other)->where('state', 'accepted')->exists(), 'Choisissez une relation réciproque dont l’identité est vérifiée.');
            $row = WorkAttestation::where('publication_id', $post->id)->where('attester_id', $other->id)->lockForUpdate()->first();
            $this->require(! $row || (! in_array($row->state, ['pending', 'confirmed'], true) && $row->updated_at->lte(now()->subDays(7))), 'Une demande existe déjà ou a été traitée depuis moins de sept jours.');
            $this->require($row || WorkAttestation::where('publication_id', $post->id)->count() < 3, 'Trois personnes au plus peuvent être sollicitées pour cette réalisation.');
            $this->limit('trust-work:'.$actor->id, 5);
            WorkAttestation::updateOrCreate(['publication_id' => $post->id, 'attester_id' => $other->id], ['author_id' => $actor->id, 'claim' => $claim, 'performed_on' => $date, 'state' => 'pending', 'revision' => ($row?->revision ?? 0) + 1, 'role' => null, 'identity_revision' => null, 'confirmed_at' => null, 'reason' => null]);
            $this->notify($other);
        });
    }

    public function decideWork(User $actor, string $id, int $revision, string $decision, string $role = '', bool $consent = false): void
    {
        $initial = WorkAttestation::where(fn ($q) => $q->where('author_id', $actor->id)->orWhere('attester_id', $actor->id))->findOrFail($id);
        $other = User::findOrFail($actor->id === $initial->author_id ? $initial->attester_id : $initial->author_id);
        $this->lockedPeople($actor, $other, function (User $actor, User $other) use ($id, $revision, $decision, $role, $consent) {
            $row = WorkAttestation::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->version($row, $revision);
            abort_unless(in_array($decision, ['confirm', 'decline', 'withdraw'], true), 422);
            Gate::forUser($actor)->authorize($decision === 'withdraw' ? 'withdraw' : 'confirm', $row);
            if ($decision === 'withdraw') {
                $this->require(in_array($row->state, ['pending', 'confirmed'], true), 'Cette référence est déjà retirée.');
                $values = ['state' => 'withdrawn', 'reason' => 'Consentement retiré par un participant.'];
            } elseif ($decision === 'decline') {
                $this->require($row->state === 'pending', 'Cette demande n’est plus en attente.');
                $values = ['state' => 'declined'];
            } else {
                $identity = $this->identity($actor);
                $this->require($row->state === 'pending' || ($row->state === 'confirmed' && $identity && $row->identity_revision !== $identity->revision), 'Une nouvelle confirmation n’est pas nécessaire ou le consentement a été retiré.');
                $this->require($identity && $other->isActive() && ! People::blocked($actor, $other, fresh: true), 'Votre identité doit être vérifiée et le contact disponible.');
                $this->require($consent && array_key_exists($role, WorkAttestation::ROLES), 'Lisez l’intervention, choisissez votre rôle et confirmez explicitement.');
                $post = $row->publication;
                Gate::forUser($actor)->authorize('view', $post);
                $this->require(! $post->isHidden() && $post->type === PublicationType::PersonalWork && ! $post->zumra_id, 'Cette réalisation ne peut plus recevoir de confirmation.');
                $values = ['state' => 'confirmed', 'role' => $role, 'identity_revision' => $identity->revision, 'confirmed_at' => now()];
            }
            $row->update($values + ['revision' => $row->revision + 1]);
            $this->notify($other);
        });
    }

    public function revokeWork(User $actor, string $id, int $revision, string $reason): void
    {
        DB::transaction(function () use ($actor, $id, $revision, $reason) {
            $row = WorkAttestation::whereKey($id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor->fresh())->authorize('review', $row);
            $this->version($row, $revision);
            $this->require(in_array($row->state, ['pending', 'confirmed'], true), 'Cette référence est déjà inactive.');
            $row->update(['state' => 'revoked', 'reason' => $this->text($reason, 500), 'revision' => $row->revision + 1]);
            AdminJournal::record($actor, 'trust.work_revoked', $row->publication, $reason, ['reference' => $row->id]);
            $this->notify($row->author);
            $this->notify($row->attester);
        }, 3);
    }

    public function reopenWork(User $actor, string $id, int $revision, string $reason): void
    {
        DB::transaction(function () use ($actor, $id, $revision, $reason) {
            $row = WorkAttestation::whereKey($id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor->fresh())->authorize('review', $row);
            $this->version($row, $revision);
            $this->require($row->state === 'revoked', 'Seule une référence retirée par GAMAD peut être réexaminée.');
            $this->require(TrustReport::where('work_attestation_id', $row->id)->whereIn('reporter_id', [$row->author_id, $row->attester_id])->where('category', 'appeal')->where('state', 'open')->exists(), 'Un recours d’un participant est nécessaire.');
            $this->require(! People::blocked($row->author, $row->attester, fresh: true), 'Le contact n’est pas disponible.');
            $row->update(['state' => 'pending', 'reason' => $this->text($reason, 500), 'revision' => $row->revision + 1, 'identity_revision' => null, 'confirmed_at' => null, 'role' => null]);
            AdminJournal::record($actor, 'trust.work_reopened', $row->publication, $reason, ['reference' => $row->id]);
            $this->notify($row->author);
            $this->notify($row->attester);
        }, 3);
    }

    public function report(User $actor, User $subject, ?WorkAttestation $work, string $category, string $details): void
    {
        abort_unless($actor->fresh()->isActive(), 403);
        if (! ($category === 'appeal' && $work && in_array($actor->id, [$work->author_id, $work->attester_id], true))) {
            Gate::forUser($actor)->authorize('view', $subject->ensureProfile());
        }
        $this->require(array_key_exists($category, TrustReport::CATEGORIES), 'Choisissez le motif du signalement.');
        if ($work) {
            abort_unless($work->author_id === $subject->id, 404);
            abort_unless(in_array($actor->id, [$work->author_id, $work->attester_id], true) || WorkAttestation::visibleTo($actor)->whereKey($work->id)->exists(), 404);
            $this->require(in_array($category, ['reference', 'appeal'], true), 'Choisissez Référence contestée ou Recours.');
        }
        $this->require($category === 'appeal' ? in_array($actor->id, [$subject->id, $work?->attester_id], true) : $actor->id !== $subject->id, 'Le recours est réservé aux personnes concernées.');
        $details = $this->text($details, 500);
        DB::transaction(function () use ($actor, $subject, $work, $category, $details) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $target = $work ? 'work:'.$work->id : 'profile:'.$subject->id;
            $this->require(! TrustReport::where('reporter_id', $actor->id)->where('target_key', $target)->where('state', 'open')->exists(), 'Votre demande est déjà en cours d’examen.');
            $this->limit('trust-report:'.$actor->id, 5);
            TrustReport::create(['reporter_id' => $actor->id, 'subject_id' => $subject->id, 'work_attestation_id' => $work?->id, 'target_key' => $target, 'category' => $category, 'details' => $details]);
            $this->reviewAlert();
        }, 3);
    }

    public function closeReport(User $actor, string $id, int $revision, string $response): void
    {
        DB::transaction(function () use ($actor, $id, $revision, $response) {
            $row = TrustReport::whereKey($id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor->fresh())->authorize('review', $row);
            $this->version($row, $revision);
            $this->require($row->state === 'open', 'Ce dossier est déjà clos.');
            $response = $this->text($response, 500);
            $row->update(['state' => 'closed', 'revision' => $row->revision + 1, 'response' => $response, 'reviewer_id' => $actor->id, 'closed_at' => now()]);
            AdminJournal::record($actor, 'trust.report_closed', $row->subject, $response, ['report' => $row->id]);
            $this->notify($row->reporter);
        }, 3);
    }

    private function lockedPeople(User $actor, User $other, callable $callback): void
    {
        abort_if($actor->id === $other->id, 403);
        DB::transaction(function () use ($actor, $other, $callback) {
            $users = User::whereIn('id', [$actor->id, $other->id])->orderBy('id')->lockForUpdate()->get();
            abort_unless($users->count() === 2, 404);
            $callback($users->firstWhere('id', $actor->id), $users->firstWhere('id', $other->id));
        }, 3);
    }

    private function version($row, int $revision): void
    {
        $this->require($row->revision === $revision, 'Cette demande a changé. Rechargez la page avant de décider.');
    }

    private function text(string $value, int $max): string
    {
        $value = Normalizer::multiline($value);
        $this->require($value !== '' && mb_strlen($value) <= $max, 'Écrivez un texte de 1 à '.$max.' caractères, sans numéro de pièce ni donnée sensible.');

        return $value;
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['trust' => $message]);
        }
    }

    private function limit(string $key, int $max): void
    {
        $this->require(! RateLimiter::tooManyAttempts($key, $max), 'Vous avez atteint la limite quotidienne. Réessayez demain.');
        RateLimiter::hit($key, 86400);
    }

    private function notify(User $user, NotificationKind $kind = NotificationKind::TrustUpdated): void
    {
        if (! UserNotification::where('user_id', $user->id)->where('kind', $kind->value)->whereNull('read_at')->exists()) {
            app(Notifier::class)->push($user, $kind);
        }
    }

    private function reviewAlert(): void
    {
        User::active()->where('platform_role', 'super_admin')->each(fn ($u) => $this->notify($u, NotificationKind::TrustReview));
    }
}
