<?php

namespace App\Services;

use App\Enums\BeneficiaryKind;
use App\Enums\HelpKind;
use App\Enums\NeedState;
use App\Enums\NotificationKind;
use App\Enums\ProposalState;
use App\Models\City;
use App\Models\Company;
use App\Models\ContributionProposal;
use App\Models\Conversation;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Publication;
use App\Models\User;
use App\Models\Zumra;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Proposer, retirer, accepter, refuser, déclarer et confirmer une contribution (C1, D-207). Chaque règle est tenue ici, côté serveur,
 * sous verrou de la proposition : deux décisions simultanées ne peuvent pas se contredire. Aucune somme n'est enregistrée et rien
 * n'est payé : une intention financière reste une intention. Aucun engagement ne devient une réalisation par le seul fait d'envoyer
 * ou d'accepter une proposition.
 */
class ContributionProposals
{
    public function __construct(private readonly Notifier $notifier, private readonly ContributionAccess $access) {}

    /**
     * Propose une contribution, spontanée ou en réponse à un besoin (`need` : le besoin ; sinon `beneficiary` : [type, identifiant]).
     *
     * @param  array{kind?: ?string, offer?: ?string, message?: ?string, visibility?: ?string, domain_id?: ?string, city_id?: ?string, need?: ?Publication, beneficiary?: array{0: string, 1: ?string}}  $data
     *
     * @throws ValidationException
     */
    public function propose(User $user, array $data): ContributionProposal
    {
        $this->guardMember($user);

        $kind = HelpKind::tryFrom((string) ($data['kind'] ?? ''));

        if (! $kind) {
            throw ValidationException::withMessages(['kind' => 'Choisissez comment vous souhaitez contribuer.']);
        }

        $offer = Normalizer::multiline((string) ($data['offer'] ?? ''));
        $message = Normalizer::multiline((string) ($data['message'] ?? ''));

        if ($offer === '') {
            throw ValidationException::withMessages(['offer' => 'Dites ce que vous proposez concrètement.']);
        }

        if (mb_strlen($offer) > ContributionProposal::OFFER_MAX) {
            throw ValidationException::withMessages(['offer' => 'Cette description ne peut pas dépasser '.ContributionProposal::OFFER_MAX.' caractères.']);
        }

        if (mb_strlen($message) > ContributionProposal::MESSAGE_MAX) {
            throw ValidationException::withMessages(['message' => 'Un message ne peut pas dépasser '.ContributionProposal::MESSAGE_MAX.' caractères.']);
        }

        $visibility = $data['visibility'] ?? ContributionProposal::PRIVATE;

        if (! in_array($visibility, [ContributionProposal::PRIVATE, ContributionProposal::PUBLIC], true)) {
            throw ValidationException::withMessages(['visibility' => 'Choisissez qui peut voir votre proposition.']);
        }

        $need = $data['need'] ?? null;
        [$beneficiaryKind, $beneficiary] = $need instanceof Publication
            ? $this->needTarget($user, $need)
            : $this->resolveTarget($user, $data['beneficiary'] ?? []);

        if ($this->access->represents($user, $beneficiaryKind, $beneficiary)) {
            throw ValidationException::withMessages(['beneficiary' => 'Vous représentez déjà ce bénéficiaire : publiez plutôt un besoin.']);
        }

        $this->guardKind($kind, $beneficiaryKind, $need);
        $this->guardPublic($visibility, $beneficiaryKind, $need);

        $domainId = $this->activeId(Domain::class, $data['domain_id'] ?? null, 'domain_id', 'Choisissez un domaine dans la liste.');
        $cityId = $this->activeId(City::class, $data['city_id'] ?? null, 'city_id', 'Choisissez une localité dans la liste.');

        $this->throttle($user);

        return DB::transaction(function () use ($user, $kind, $offer, $message, $visibility, $need, $beneficiaryKind, $beneficiary, $domainId, $cityId) {
            if ($need) {
                $locked = Publication::query()->whereKey($need->getKey())->lockForUpdate()->firstOrFail();

                if (! ($locked->need_state?->isActive() ?? false) || $locked->isHidden()) {
                    throw ValidationException::withMessages(['need' => 'Ce besoin n’accepte plus de proposition.']);
                }
            } else {
                // Les propositions spontanées d'une même personne s'enchaînent : une seule proposition ouverte par bénéficiaire et par forme.
                User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            }

            $this->guardDuplicate($user, $kind, $beneficiaryKind, $beneficiary, $need);

            $proposal = new ContributionProposal([
                'kind' => $kind,
                'offer' => $offer,
                'message' => $message === '' ? null : $message,
                'visibility' => $visibility,
                'domain_id' => $domainId,
                'city_id' => $cityId,
                'official' => $beneficiaryKind === BeneficiaryKind::Gamad,
            ]);
            $proposal->state = ProposalState::Proposed;
            $proposal->author()->associate($user);
            $proposal->need()->associate($need);

            match ($beneficiaryKind) {
                BeneficiaryKind::Zumra => $proposal->zumra()->associate($beneficiary),
                BeneficiaryKind::Project => $proposal->project()->associate($beneficiary),
                BeneficiaryKind::Company => $proposal->company()->associate($beneficiary),
                BeneficiaryKind::Person => $proposal->person()->associate($beneficiary),
                BeneficiaryKind::Gamad => null,
            };
            $proposal->save();

            $this->access->representatives($beneficiaryKind, $beneficiary)
                ->each(fn (User $to) => $this->notifier->proposal($to, NotificationKind::ContributionProposed, $user, $proposal));

            return $proposal;
        });
    }

    /** Retire sa proposition : tant qu'elle n'est pas confirmée comme réalisée. */
    public function withdraw(User $user, ContributionProposal $proposal): ContributionProposal
    {
        return $this->transition($proposal, function (ContributionProposal $locked) use ($user) {
            Gate::forUser($user)->authorize('withdraw', $locked);

            $locked->forceFill(['state' => ProposalState::Withdrawn])->save();

            if ($locked->wasChanged('state')) {
                $this->access->representatives(...$this->access->beneficiary($locked))
                    ->each(fn (User $to) => $this->notifier->proposal($to, NotificationKind::ContributionWithdrawn, $user, $locked));
            }
        });
    }

    /** Accepte : le besoin passe « en cours » et une conversation privée s'ouvre. L'acceptation n'est pas une réalisation. */
    public function accept(User $actor, ContributionProposal $proposal, ?string $note = null): ContributionProposal
    {
        $note = $this->note($note);

        return $this->transition($proposal, function (ContributionProposal $locked) use ($actor, $note) {
            Gate::forUser($actor)->authorize('decide', $locked);

            $locked->forceFill(['state' => ProposalState::Accepted, 'decided_by' => $actor->getKey(), 'decided_at' => now(), 'decision_note' => $note])->save();

            $need = $locked->need_id ? Publication::query()->whereKey($locked->need_id)->lockForUpdate()->first() : null;

            if ($need && $need->need_state === NeedState::Open) {
                $need->update(['need_state' => NeedState::InProgress]);
            }

            $conversation = $this->openConversation($locked, $actor);
            $this->notifier->proposal($locked->author, NotificationKind::ContributionAccepted, $actor, $locked, $conversation);
        });
    }

    public function decline(User $actor, ContributionProposal $proposal, ?string $note = null): ContributionProposal
    {
        $note = $this->note($note);

        return $this->transition($proposal, function (ContributionProposal $locked) use ($actor, $note) {
            Gate::forUser($actor)->authorize('decide', $locked);

            $locked->forceFill(['state' => ProposalState::Declined, 'decided_by' => $actor->getKey(), 'decided_at' => now(), 'decision_note' => $note])->save();
            $this->notifier->proposal($locked->author, NotificationKind::ContributionDeclined, $actor, $locked);
        });
    }

    /** La personne qui contribue déclare avoir réalisé sa contribution : le bénéficiaire doit encore la confirmer. */
    public function declareDone(User $user, ContributionProposal $proposal): ContributionProposal
    {
        return $this->transition($proposal, function (ContributionProposal $locked) use ($user) {
            Gate::forUser($user)->authorize('declare', $locked);

            $locked->forceFill(['state' => ProposalState::Declared, 'declared_at' => now()])->save();
            $this->access->representatives(...$this->access->beneficiary($locked))
                ->each(fn (User $to) => $this->notifier->proposal($to, NotificationKind::ContributionDeclared, $user, $locked));
        });
    }

    /** Le bénéficiaire confirme explicitement la réalisation : seule cette confirmation compte comme contribution réalisée. */
    public function confirm(User $actor, ContributionProposal $proposal): ContributionProposal
    {
        return $this->transition($proposal, function (ContributionProposal $locked) use ($actor) {
            Gate::forUser($actor)->authorize('confirm', $locked);

            $locked->forceFill(['state' => ProposalState::Realized, 'realized_at' => now(), 'realized_by' => $actor->getKey()])->save();
            $this->notifier->proposal($locked->author, NotificationKind::ContributionRealized, $actor, $locked);
        });
    }

    /**
     * Un besoin devient satisfait ou clos : les propositions encore sans réponse sont refusées d'office (le besoin n'existe plus pour
     * elles) ; celles déjà acceptées, déclarées ou confirmées restent intactes.
     */
    public function needClosed(Publication $need): void
    {
        ContributionProposal::query()->where('need_id', $need->getKey())->where('state', ProposalState::Proposed->value)->get()
            ->each(function (ContributionProposal $proposal) use ($need) {
                $proposal->forceFill(['state' => ProposalState::Declined, 'decided_at' => now(), 'decision_note' => 'Ce besoin est clos.'])->save();
                $this->notifier->proposal($proposal->author, NotificationKind::ContributionDeclined, null, $proposal);
            });
    }

    /** Transition sous verrou : l'état est relu avant d'agir, et la valeur retournée est à jour. */
    private function transition(ContributionProposal $proposal, \Closure $action): ContributionProposal
    {
        return DB::transaction(function () use ($proposal, $action) {
            $locked = ContributionProposal::query()->whereKey($proposal->getKey())->lockForUpdate()->firstOrFail();
            $action($locked);
            $proposal->setRawAttributes($locked->fresh()->getAttributes(), true);

            return $proposal;
        });
    }

    private function openConversation(ContributionProposal $proposal, User $acceptor): Conversation
    {
        $conversation = Conversation::query()->where('contribution_proposal_id', $proposal->getKey())->first();

        if (! $conversation) {
            $conversation = new Conversation(['owner_id' => $acceptor->getKey(), 'helper_id' => $proposal->user_id, 'origin' => 'contribution']);
            $conversation->contributionProposal()->associate($proposal);
            $conversation->publication()->associate($proposal->need_id ? Publication::query()->find($proposal->need_id) : null);
            $conversation->save();
        }

        return $conversation;
    }

    /**
     * Le bénéficiaire d'une réponse à un besoin : celui du besoin, à condition que le besoin soit visible, ouvert et son bénéficiaire accessible.
     *
     * @return array{0: BeneficiaryKind, 1: ?Model}
     */
    private function needTarget(User $user, Publication $need): array
    {
        if (! $need->isNeed() || ! Gate::forUser($user)->allows('view', $need) || $need->isHidden()) {
            throw ValidationException::withMessages(['need' => 'Ce besoin n’existe pas ou n’est pas accessible.']);
        }

        if (! ($need->need_state?->isActive() ?? false)) {
            throw ValidationException::withMessages(['need' => 'Ce besoin n’accepte plus de proposition.']);
        }

        [$kind, $beneficiary] = $this->access->beneficiary($need);

        if (! $this->access->exists($user, $kind, $beneficiary)) {
            throw ValidationException::withMessages(['need' => 'Ce besoin n’existe pas ou n’est pas accessible.']);
        }

        return [$kind, $beneficiary];
    }

    /**
     * Le bénéficiaire d'une proposition spontanée, avec les conditions propres à chaque type.
     *
     * @param  array{0?: string, 1?: ?string}  $target
     * @return array{0: BeneficiaryKind, 1: ?Model}
     */
    private function resolveTarget(User $user, array $target): array
    {
        $kind = BeneficiaryKind::tryFrom((string) ($target[0] ?? ''));
        $id = $target[1] ?? null;

        if (! $kind) {
            throw ValidationException::withMessages(['beneficiary' => 'Choisissez à qui vous souhaitez contribuer.']);
        }

        $missing = ValidationException::withMessages(['beneficiary' => 'Ce bénéficiaire n’existe pas ou n’est pas accessible.']);

        if ($kind !== BeneficiaryKind::Gamad && (! is_string($id) || ! Str::isUuid($id))) {
            throw $missing;
        }

        $beneficiary = match ($kind) {
            BeneficiaryKind::Gamad => null,
            BeneficiaryKind::Zumra => Zumra::query()->find($id),
            BeneficiaryKind::Project => Project::query()->find($id),
            BeneficiaryKind::Company => Company::query()->find($id),
            BeneficiaryKind::Person => User::query()->find($id),
        };

        if (! $this->access->exists($user, $kind, $beneficiary)) {
            throw $missing;
        }

        if ($kind === BeneficiaryKind::Person) {
            // Une personne ne reçoit une aide spontanée que si elle l'a autorisé, et jamais d'une personne qu'elle a bloquée.
            if (! $beneficiary->profile?->accepts_contributions || People::blocked($user, $beneficiary)) {
                throw ValidationException::withMessages(['beneficiary' => 'Cette personne ne reçoit pas de proposition d’aide pour le moment.']);
            }
        }

        if ($kind === BeneficiaryKind::Project && $beneficiary instanceof Project && ! $beneficiary->isShowcasePublished()) {
            throw $missing;
        }

        return [$kind, $beneficiary];
    }

    /**
     * Une intention financière n'est ni un paiement ni un investissement : elle n'existe qu'en réponse à un besoin financier, ou
     * spontanément pour GAMAD, une ZUMRA ou un projet. Jamais pour une entreprise, jamais pour une personne hors besoin.
     */
    private function guardKind(HelpKind $kind, BeneficiaryKind $beneficiary, ?Publication $need): void
    {
        if ($kind !== HelpKind::Financial) {
            return;
        }

        if ($beneficiary === BeneficiaryKind::Company) {
            throw ValidationException::withMessages(['kind' => 'Une entreprise ne reçoit pas de soutien financier dans GAMAD : proposez plutôt vos compétences, votre temps ou du matériel.']);
        }

        if ($need && $need->help_kind !== HelpKind::Financial->value) {
            throw ValidationException::withMessages(['kind' => 'Ce besoin ne demande pas de soutien financier.']);
        }

        if (! $need && $beneficiary === BeneficiaryKind::Person) {
            throw ValidationException::withMessages(['kind' => 'Un soutien financier à une personne se propose en réponse à un besoin qu’elle a publié.']);
        }
    }

    /** Une proposition publique ne révèle pas une personne : seule une réponse à un besoin public, ou un bénéficiaire collectif, peut l'être. */
    private function guardPublic(string $visibility, BeneficiaryKind $beneficiary, ?Publication $need): void
    {
        if ($visibility !== ContributionProposal::PUBLIC) {
            return;
        }

        if ($need && $need->visibility !== Publication::PUBLIC) {
            throw ValidationException::withMessages(['visibility' => 'Ce besoin n’est pas public : votre proposition reste privée.']);
        }

        if (! $need && $beneficiary === BeneficiaryKind::Person) {
            throw ValidationException::withMessages(['visibility' => 'Une proposition adressée à une personne reste privée.']);
        }
    }

    private function guardDuplicate(User $user, HelpKind $kind, BeneficiaryKind $beneficiaryKind, ?Model $beneficiary, ?Publication $need): void
    {
        $open = ContributionProposal::query()->where('user_id', $user->getKey())
            ->whereIn('state', [ProposalState::Proposed->value, ProposalState::Accepted->value, ProposalState::Declared->value]);

        $exists = $need
            ? (clone $open)->where('need_id', $need->getKey())->exists()
            : (clone $open)->whereNull('need_id')->where('kind', $kind->value)->where(function ($query) use ($beneficiaryKind, $beneficiary) {
                match ($beneficiaryKind) {
                    BeneficiaryKind::Gamad => $query->where('official', true),
                    BeneficiaryKind::Zumra => $query->where('zumra_id', $beneficiary->getKey()),
                    BeneficiaryKind::Project => $query->where('project_id', $beneficiary->getKey()),
                    BeneficiaryKind::Company => $query->where('company_id', $beneficiary->getKey()),
                    BeneficiaryKind::Person => $query->where('person_id', $beneficiary->getKey()),
                };
            })->exists();

        if ($exists) {
            throw ValidationException::withMessages(['offer' => 'Vous avez déjà une proposition en cours pour cela. Retrouvez-la dans Mes contributions.']);
        }
    }

    private function guardMember(User $user): void
    {
        if (! $user->isActive() || ! $this->access->moduleOpen($user)) {
            abort(404);
        }
    }

    private function note(?string $note): ?string
    {
        $note = Normalizer::multiline((string) $note);

        if (mb_strlen($note) > ContributionProposal::NOTE_MAX) {
            throw ValidationException::withMessages(['note' => 'Un mot ne peut pas dépasser '.ContributionProposal::NOTE_MAX.' caractères.']);
        }

        return $note === '' ? null : $note;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function activeId(string $model, mixed $id, string $field, string $message): ?string
    {
        if (blank($id)) {
            return null;
        }

        if (! is_string($id) || ! Str::isUuid($id) || ! $model::query()->whereKey($id)->where('active', true)->exists()) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $id;
    }

    private function throttle(User $user): void
    {
        $limits = [
            [config('gamad.limits.contribution_proposals_per_hour'), 3600, 'Vous proposez trop vite. Réessayez dans un moment.'],
            [config('gamad.limits.contribution_proposals_per_day'), 86400, 'Vous avez atteint la limite de propositions pour aujourd’hui.'],
        ];

        foreach ($limits as $index => [$max, , $message]) {
            if (RateLimiter::tooManyAttempts("contribution-proposal:{$user->getKey()}:$index", $max)) {
                throw ValidationException::withMessages(['offer' => $message]);
            }
        }

        foreach ($limits as $index => [, $window]) {
            RateLimiter::hit("contribution-proposal:{$user->getKey()}:$index", $window);
        }
    }
}
