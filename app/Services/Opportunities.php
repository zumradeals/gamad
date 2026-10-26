<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Enums\OpportunityKind;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\OpportunityResponse;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Module Opportunités : toutes les règles sont ici, côté serveur. Les entreprises vérifiées publient ; les personnes
 * consultent, sauvegardent et répondent. Aucune note, aucun classement, aucun pourcentage de compatibilité.
 */
class Opportunities
{
    public function __construct(private Notifier $notifier) {}

    /** Normalise et valide les champs saisis. Un brouillon n'exige que le type et l'intitulé. */
    public function clean(array $data, bool $forPublication): array
    {
        $data['title'] = Normalizer::display((string) ($data['title'] ?? ''));
        foreach (['description', 'missions', 'profile', 'qualifications', 'conditions'] as $field) {
            $data[$field] = Normalizer::multiline((string) ($data[$field] ?? ''));
        }
        $data['experience'] = Normalizer::display((string) ($data['experience'] ?? ''));
        $data['skills'] = collect($data['skills'] ?? [])->map(fn ($skill) => Normalizer::display((string) $skill))->filter()->unique(fn ($s) => Normalizer::key($s))->values()->all();
        foreach (['work_mode', 'workload', 'city_id', 'domain_id', 'deadline', 'external_url'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? $data[$field] : null;
        }
        $data['apply_mode'] = ($data['apply_mode'] ?? 'gamad') === 'external' ? 'external' : 'gamad';

        $rules = [
            'kind' => ['required', Rule::enum(OpportunityKind::class)],
            'title' => ['required', 'string', 'max:160'],
            'description' => [$forPublication ? 'required' : 'nullable', 'string', 'max:1000'],
            'missions' => ['nullable', 'string', 'max:2000'],
            'profile' => ['nullable', 'string', 'max:2000'],
            'skills' => ['array', 'max:'.Opportunity::MAX_SKILLS],
            'skills.*' => ['string', 'max:60'],
            'experience' => ['nullable', 'string', 'max:160'],
            'qualifications' => ['nullable', 'string', 'max:1000'],
            'conditions' => ['nullable', 'string', 'max:1000'],
            'work_mode' => ['nullable', Rule::in(array_keys(Opportunity::WORK_MODES))],
            'workload' => ['nullable', Rule::in(array_keys(Opportunity::WORKLOADS))],
            'city_id' => ['nullable', Rule::exists('cities', 'id')->where('active', true)],
            'domain_id' => ['nullable', Rule::exists('domains', 'id')->where('active', true)],
            'deadline' => ['nullable', 'date_format:Y-m-d', $forPublication ? 'after_or_equal:'.Carbon::today('Africa/Abidjan')->toDateString() : 'date'],
            'apply_mode' => ['required', Rule::in(['gamad', 'external'])],
            'external_url' => ['nullable', 'string', 'max:500'],
        ];
        $messages = [
            'title.required' => 'Indiquez le poste ou le besoin.',
            'title.max' => 'Le titre est trop long (160 caractères au plus).',
            'description.required' => 'Expliquez ce que vous recherchez.',
            'description.max' => 'La description est trop longue (1 000 caractères au plus).',
            'skills.max' => 'Huit compétences au plus.',
            'deadline.after_or_equal' => 'La date limite ne peut pas être dans le passé.',
            'deadline.date_format' => 'Choisissez une date valide.',
            'kind.required' => 'Choisissez le type d’opportunité.',
        ];
        $clean = Validator::make($data, $rules, $messages)->validate();

        if ($clean['apply_mode'] === 'external') {
            $clean['external_url'] = $this->safeUrl($clean['external_url'] ?? null);
        } else {
            $clean['external_url'] = null;
        }

        return $clean;
    }

    /** Lien externe de candidature : https seulement, sans identifiants intégrés, jamais vers une adresse interne. */
    public function safeUrl(?string $url): string
    {
        $url = trim((string) $url);
        $parts = filter_var($url, FILTER_VALIDATE_URL) ? parse_url($url) : false;
        $host = strtolower((string) ($parts['host'] ?? ''));
        $invalid = ! $parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || $host === ''
            || ! str_contains($host, '.') || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')
            || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
        if ($invalid) {
            throw ValidationException::withMessages(['external_url' => 'Indiquez un lien sécurisé complet, commençant par https://.']);
        }

        return $url;
    }

    public function saveDraft(User $user, Company $company, array $data, ?Opportunity $opportunity = null): Opportunity
    {
        $opportunity ? Gate::forUser($user)->authorize('manage', $opportunity) : Gate::forUser($user)->authorize('manageFor', [Opportunity::class, $company]);
        $clean = $this->clean($data, false);

        return $this->persist($user, $company, $clean, $opportunity);
    }

    public function publish(User $user, Company $company, array $data, ?Opportunity $opportunity = null): Opportunity
    {
        Gate::forUser($user)->authorize('publishFor', [Opportunity::class, $company]);
        if ($opportunity) {
            Gate::forUser($user)->authorize('manage', $opportunity);
            abort_if($opportunity->company_id !== $company->id, 404);
        }
        $clean = $this->clean($data, true);

        return DB::transaction(function () use ($user, $company, $clean, $opportunity) {
            $saved = $this->persist($user, $company, $clean, $opportunity);
            $saved->forceFill(['status' => Opportunity::PUBLISHED, 'published_at' => $saved->published_at ?? now(), 'closed_at' => null])->save();

            return $saved;
        });
    }

    private function persist(User $user, Company $company, array $clean, ?Opportunity $opportunity): Opportunity
    {
        if ($opportunity) {
            abort_if($opportunity->company_id !== $company->id, 404);
            $opportunity->fill($clean)->save();

            return $opportunity;
        }

        $created = new Opportunity($clean);
        $created->company()->associate($company);
        $created->author()->associate($user);
        $created->save();

        return $created;
    }

    /** Clôturer : l'opportunité n'est plus ouverte, ses réponses restent consultables. */
    public function close(User $user, Opportunity $opportunity): void
    {
        Gate::forUser($user)->authorize('manage', $opportunity);
        abort_unless($opportunity->status === Opportunity::PUBLISHED, 422);
        $opportunity->forceFill(['status' => Opportunity::CLOSED, 'closed_at' => now()])->save();
    }

    public function reopen(User $user, Opportunity $opportunity): void
    {
        Gate::forUser($user)->authorize('publish', $opportunity);
        abort_unless($opportunity->status === Opportunity::CLOSED, 422);
        if ($opportunity->expired()) {
            throw ValidationException::withMessages(['deadline' => 'La date limite est dépassée : modifiez-la avant de rouvrir.']);
        }
        $opportunity->forceFill(['status' => Opportunity::PUBLISHED, 'closed_at' => null])->save();
    }

    /** Archiver : retire l'opportunité du public ; les réponses restent conservées pour l'entreprise. */
    public function archive(User $user, Opportunity $opportunity): void
    {
        Gate::forUser($user)->authorize('manage', $opportunity);
        abort_if($opportunity->status === Opportunity::DRAFT, 422);
        $opportunity->forceFill(['status' => Opportunity::ARCHIVED, 'closed_at' => $opportunity->closed_at ?? now()])->save();
    }

    public function deleteDraft(User $user, Opportunity $opportunity): void
    {
        Gate::forUser($user)->authorize('manage', $opportunity);
        abort_unless($opportunity->status === Opportunity::DRAFT, 422);
        $opportunity->delete();
    }

    public function toggleSave(User $user, Opportunity $opportunity, bool $save): void
    {
        Gate::forUser($user)->authorize('view', $opportunity);
        abort_unless($user->fresh()->isActive(), 403);
        if ($save) {
            DB::table('opportunity_saves')->insertOrIgnore(['user_id' => $user->id, 'opportunity_id' => $opportunity->id, 'created_at' => now()]);
        } else {
            DB::table('opportunity_saves')->where('user_id', $user->id)->where('opportunity_id', $opportunity->id)->delete();
        }
    }

    public function respond(User $user, Opportunity $opportunity, ?string $message, ?UploadedFile $attachment): OpportunityResponse
    {
        Gate::forUser($user)->authorize('respond', $opportunity);
        $message = Normalizer::multiline((string) $message);
        if (mb_strlen($message) > 1000) {
            throw ValidationException::withMessages(['message' => 'Votre message est trop long (1 000 caractères au plus).']);
        }
        $path = $this->storeAttachment($attachment);

        try {
            return DB::transaction(function () use ($user, $opportunity, $message, $path) {
                $existing = OpportunityResponse::query()->where('opportunity_id', $opportunity->id)->where('user_id', $user->id)->lockForUpdate()->first();
                if ($existing && $existing->state !== OpportunityResponse::WITHDRAWN) {
                    throw ValidationException::withMessages(['message' => 'Vous avez déjà répondu à cette opportunité.']);
                }
                $response = $existing ?? new OpportunityResponse;
                $response->deleteAttachment();
                $response->fill(['message' => $message === '' ? null : $message]);
                $response->opportunity()->associate($opportunity);
                $response->user()->associate($user);
                $response->forceFill(['attachment_path' => $path, 'state' => OpportunityResponse::SENT, 'seen_at' => null])->save();
                $this->notifier->opportunity($opportunity->company->owner, NotificationKind::OpportunityResponseReceived, $user, $opportunity);

                return $response;
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $error;
        }
    }

    public function withdraw(User $user, OpportunityResponse $response): void
    {
        abort_unless($response->user_id === $user->id, 404);
        abort_if($response->state === OpportunityResponse::WITHDRAWN, 422);
        $response->deleteAttachment();
        $response->forceFill(['state' => OpportunityResponse::WITHDRAWN, 'attachment_path' => null, 'message' => null])->save();
    }

    /** L'entreprise a lu la réponse. */
    public function markSeen(User $user, OpportunityResponse $response): void
    {
        Gate::forUser($user)->authorize('viewResponses', $response->opportunity);
        if ($response->state === OpportunityResponse::SENT) {
            $response->forceFill(['state' => OpportunityResponse::SEEN, 'seen_at' => now()])->save();
        }
    }

    /** Proposer un échange : réutilise la demande de relation (consentement avant toute conversation privée). */
    public function contact(User $user, OpportunityResponse $response, People $people): void
    {
        Gate::forUser($user)->authorize('viewResponses', $response->opportunity);
        abort_if($response->state === OpportunityResponse::WITHDRAWN, 422);
        $opportunity = $response->opportunity;
        $people->request($user, $response->user, Str::limit('Entreprise '.$opportunity->company->name.' : suite à votre réponse à « '.$opportunity->title.' ».', 270, ''));
        $response->forceFill(['state' => OpportunityResponse::CONTACTED, 'seen_at' => $response->seen_at ?? now()])->save();
        $this->notifier->opportunity($response->user, NotificationKind::OpportunityResponseContacted, $user, $opportunity);
    }

    private function storeAttachment(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }
        Validator::make(['attachment' => $file], ['attachment' => ['file', 'max:5120', 'mimetypes:application/pdf']], [
            'attachment.*' => 'Joignez un document PDF de 5 Mo au plus.',
        ])->validate();
        $bytes = file_get_contents($file->getRealPath());
        if (! str_starts_with((string) $bytes, '%PDF-')) {
            throw ValidationException::withMessages(['attachment' => 'Joignez un document PDF de 5 Mo au plus.']);
        }
        $path = 'opportunity-responses/'.Str::uuid().'.pdf';
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw ValidationException::withMessages(['attachment' => 'Le document n’a pas pu être enregistré. Réessayez.']);
        }

        return $path;
    }
}
