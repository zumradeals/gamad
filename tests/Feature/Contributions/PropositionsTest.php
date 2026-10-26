<?php

use App\Enums\NotificationKind;
use App\Enums\ProposalState;
use App\Models\Allocation;
use App\Models\Contribution;
use App\Models\ContributionProposal;
use App\Models\Conversation;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\ProjectMember;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Schema;
use App\Models\Wallet;
use App\Models\User;
use App\Services\ContributionProposals;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** C1 (D-207) : proposer, accepter, refuser, retirer, déclarer et confirmer une contribution. */
beforeEach(function () {
    c1Module('public');
    c1ModuleZumra('public');
    $this->service = app(ContributionProposals::class);
    $this->awa = person('Awa Traoré');
    $this->yao = person('Yao Kouassi');
    $this->moussa = person('Moussa Koné');
    $this->zumra = zumra($this->yao, 'Couture solidaire');
});

it('propose spontanément une contribution à une ZUMRA, privée par défaut, et prévient ses responsables', function () {
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id], 'message' => 'Je me présente.']);

    expect($proposition->state)->toBe(ProposalState::Proposed)
        ->and($proposition->visibility)->toBe('private')
        ->and($proposition->zumra_id)->toBe($this->zumra->id)
        ->and($proposition->need_id)->toBeNull()
        ->and($proposition->message)->toBe('Je me présente.')
        ->and(UserNotification::query()->where('user_id', $this->yao->id)->where('kind', NotificationKind::ContributionProposed->value)->count())->toBe(1)
        ->and(UserNotification::query()->where('user_id', $this->moussa->id)->count())->toBe(0);
});

it('répond à un besoin : bénéficiaire et forme d’aide viennent du besoin, et son auteur est prévenu', function () {
    $besoin = c1Besoin($this->awa, ['kind' => 'skills']);
    $proposition = c1Proposition($this->moussa, ['need' => $besoin, 'kind' => 'skills']);

    expect($proposition->need_id)->toBe($besoin->id)
        ->and($proposition->person_id)->toBe($this->awa->id)
        ->and($proposition->beneficiaryName())->toBe('Awa Traoré')
        ->and(UserNotification::query()->where('user_id', $this->awa->id)->where('kind', NotificationKind::ContributionProposed->value)->exists())->toBeTrue();
});

it('accepte : le besoin passe en cours, une conversation privée s’ouvre et la personne est prévenue, sans réalisation', function () {
    $besoin = c1Besoin($this->awa, ['kind' => 'skills']);
    $proposition = c1Proposition($this->moussa, ['need' => $besoin, 'kind' => 'skills']);

    $this->service->accept($this->awa, $proposition, 'Merci, parlons-en.');

    $proposition = $proposition->fresh();
    $conversation = Conversation::query()->where('contribution_proposal_id', $proposition->id)->first();

    expect($proposition->state)->toBe(ProposalState::Accepted)
        ->and($proposition->decided_by)->toBe($this->awa->id)
        ->and($proposition->decision_note)->toBe('Merci, parlons-en.')
        ->and($proposition->realized_at)->toBeNull()
        ->and($besoin->fresh()->need_state->value)->toBe('in_progress')
        ->and($conversation)->not->toBeNull()
        ->and($conversation->origin)->toBe('contribution')
        ->and($conversation->isParticipant($this->moussa))->toBeTrue()->and($conversation->isParticipant($this->awa))->toBeTrue()
        ->and($conversation->isParticipant($this->yao))->toBeFalse()
        ->and(UserNotification::query()->where('user_id', $this->moussa->id)->where('kind', NotificationKind::ContributionAccepted->value)->exists())->toBeTrue();
});

it('refuse, et une proposition refusée ne se décide plus', function () {
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id]]);

    $this->service->decline($this->yao, $proposition, 'Pas pour le moment.');

    expect($proposition->fresh()->state)->toBe(ProposalState::Declined)
        ->and(UserNotification::query()->where('user_id', $this->moussa->id)->where('kind', NotificationKind::ContributionDeclined->value)->exists())->toBeTrue();
    expect(fn () => $this->service->accept($this->yao, $proposition->fresh()))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->decline($this->yao, $proposition->fresh()))->toThrow(AuthorizationException::class);
});

it('retire sa proposition tant qu’elle n’est pas réalisée, et seule la personne qui l’a faite peut la retirer', function () {
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id]]);

    expect(fn () => $this->service->withdraw($this->awa, $proposition))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->withdraw($this->yao, $proposition))->toThrow(AuthorizationException::class);

    $this->service->withdraw($this->moussa, $proposition);
    expect($proposition->fresh()->state)->toBe(ProposalState::Withdrawn);
    expect(fn () => $this->service->withdraw($this->moussa, $proposition->fresh()))->toThrow(AuthorizationException::class);

    // Une proposition acceptée se retire aussi : l'engagement est volontaire des deux côtés.
    $acceptee = c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id], 'kind' => 'time']);
    $this->service->accept($this->yao, $acceptee);
    $this->service->withdraw($this->moussa, $acceptee->fresh());
    expect($acceptee->fresh()->state)->toBe(ProposalState::Withdrawn);
});

it('ne déduit jamais la réalisation de l’acceptation : déclaration, puis confirmation explicite du bénéficiaire', function () {
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id]]);

    expect(fn () => $this->service->declareDone($this->moussa, $proposition))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->confirm($this->yao, $proposition))->toThrow(AuthorizationException::class);

    $this->service->accept($this->yao, $proposition);
    expect($proposition->fresh()->state)->toBe(ProposalState::Accepted)->and($proposition->fresh()->realized_at)->toBeNull();

    // Seule la personne qui contribue déclare ; le bénéficiaire seul confirme.
    expect(fn () => $this->service->declareDone($this->yao, $proposition->fresh()))->toThrow(AuthorizationException::class);
    $this->service->declareDone($this->moussa, $proposition->fresh());
    expect($proposition->fresh()->state)->toBe(ProposalState::Declared)->and($proposition->fresh()->realized_at)->toBeNull()
        ->and(UserNotification::query()->where('user_id', $this->yao->id)->where('kind', NotificationKind::ContributionDeclared->value)->exists())->toBeTrue();

    expect(fn () => $this->service->confirm($this->moussa, $proposition->fresh()))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->confirm($this->awa, $proposition->fresh()))->toThrow(AuthorizationException::class);
    $this->service->confirm($this->yao, $proposition->fresh());

    $finale = $proposition->fresh();
    expect($finale->state)->toBe(ProposalState::Realized)->and($finale->realized_at)->not->toBeNull()->and($finale->realized_by)->toBe($this->yao->id)
        ->and(UserNotification::query()->where('user_id', $this->moussa->id)->where('kind', NotificationKind::ContributionRealized->value)->exists())->toBeTrue();

    // Une réalisation confirmée ne se retire plus.
    expect(fn () => $this->service->withdraw($this->moussa, $finale))->toThrow(AuthorizationException::class);
});

it('laisse le bénéficiaire confirmer directement une contribution acceptée qu’il reconnaît réalisée', function () {
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id]]);
    $this->service->accept($this->yao, $proposition);

    $this->service->confirm($this->yao, $proposition->fresh());

    expect($proposition->fresh()->state)->toBe(ProposalState::Realized);
});

it('refuse une proposition sur un bénéficiaire que l’on représente, et une seconde proposition identique en cours', function () {
    expect(fn () => c1Proposition($this->yao, ['beneficiary' => ['zumra', $this->zumra->id]]))->toThrow(ValidationException::class, 'représentez');

    c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id]]);
    expect(fn () => c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id]]))->toThrow(ValidationException::class, 'déjà une proposition');

    $besoin = c1Besoin($this->awa, ['kind' => 'skills']);
    c1Proposition($this->moussa, ['need' => $besoin]);
    expect(fn () => c1Proposition($this->moussa, ['need' => $besoin]))->toThrow(ValidationException::class, 'déjà une proposition');
    expect(fn () => c1Proposition($this->awa, ['need' => $besoin]))->toThrow(ValidationException::class, 'représentez');
});

it('valide la forme d’aide, la description et le message, et limite les propositions par heure', function () {
    foreach ([['kind' => ''], ['offer' => ''], ['offer' => str_repeat('a', 501)], ['message' => str_repeat('a', 501)], ['visibility' => 'secret']] as $donnees) {
        expect(fn () => c1Proposition($this->moussa, $donnees + ['beneficiary' => ['zumra', $this->zumra->id]]))->toThrow(ValidationException::class);
    }

    config(['gamad.limits.contribution_proposals_per_hour' => 1]);
    c1Proposition($this->moussa, ['beneficiary' => ['gamad', null]]);
    expect(fn () => c1Proposition($this->moussa, ['beneficiary' => ['gamad', null], 'kind' => 'time']))->toThrow(ValidationException::class, 'trop vite');
});

it('traite les propositions faites à GAMAD par le super administrateur seul', function () {
    $admin = User::factory()->superAdmin()->create();
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['gamad', null]]);

    expect($proposition->official)->toBeTrue()->and($proposition->beneficiaryName())->toBe('GAMAD')
        ->and(UserNotification::query()->where('user_id', $admin->id)->where('kind', NotificationKind::ContributionProposed->value)->exists())->toBeTrue();
    expect(fn () => $this->service->accept($this->yao, $proposition))->toThrow(AuthorizationException::class);
    $this->service->accept($admin, $proposition);
    expect($proposition->fresh()->state)->toBe(ProposalState::Accepted);
});

it('traite les propositions faites à une entreprise par son gestionnaire seul, sans jamais les assimiler à un investissement', function () {
    $patron = person('Patron Entreprise');
    $entreprise = c1Entreprise($patron);

    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['company', $entreprise->id], 'kind' => 'time']);
    expect($proposition->company_id)->toBe($entreprise->id);
    expect(fn () => $this->service->accept($this->yao, $proposition))->toThrow(AuthorizationException::class);
    $this->service->accept($patron, $proposition);
    expect($proposition->fresh()->state)->toBe(ProposalState::Accepted);

    // Une intention financière n'existe pas pour une entreprise.
    expect(fn () => c1Proposition(person('Autre Aidant'), ['beneficiary' => ['company', $entreprise->id], 'kind' => 'financial']))->toThrow(ValidationException::class, 'investissement' === 'x' ? '' : 'soutien financier');
});

it('traite les propositions faites à un projet par son porteur ou la personne habilitée, jamais par la ZUMRA d’accueil', function () {
    $porteuse = person('Fatou Porteuse');
    $projet = c1Projet($porteuse, $this->zumra);
    $collaborateur = person('Collaborateur');
    ajouteMembre($this->zumra, $collaborateur);

    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['project', $projet->id], 'kind' => 'time']);

    // Le fondateur de la ZUMRA d'accueil ne reçoit rien et ne décide rien.
    expect(UserNotification::query()->where('user_id', $this->yao->id)->where('kind', NotificationKind::ContributionProposed->value)->exists())->toBeFalse();
    expect(UserNotification::query()->where('user_id', $porteuse->id)->where('kind', NotificationKind::ContributionProposed->value)->exists())->toBeTrue();
    foreach ([$this->yao, $collaborateur, $this->awa] as $intrus) {
        expect(fn () => $this->service->accept($intrus, $proposition))->toThrow(AuthorizationException::class);
    }

    // Le porteur habilite explicitement un collaborateur.
    $member = new ProjectMember(['manages_contributions' => true]);
    $member->project()->associate($projet);
    $member->user()->associate($collaborateur);
    $member->save();

    $this->service->accept($collaborateur, $proposition);
    expect($proposition->fresh()->state)->toBe(ProposalState::Accepted)->and($proposition->fresh()->decided_by)->toBe($collaborateur->id);
});

it('n’accepte aucune proposition pour un projet resté privé ou une ZUMRA fermée', function () {
    $porteuse = person('Fatou Porteuse');
    $prive = c1Projet($porteuse, $this->zumra, 'Projet discret', publie: false);

    expect(fn () => c1Proposition($this->moussa, ['beneficiary' => ['project', $prive->id]]))->toThrow(ValidationException::class, 'n’existe pas');

    c1ModuleZumra('off');
    expect(fn () => c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id]]))->toThrow(ValidationException::class, 'n’existe pas');
});

it('reçoit les propositions d’une personne seulement si son profil l’autorise, et jamais d’une personne bloquée', function () {
    expect(fn () => c1Proposition($this->moussa, ['beneficiary' => ['person', $this->awa->id]]))->toThrow(ValidationException::class, 'ne reçoit pas');

    $this->awa->profile->forceFill(['accepts_contributions' => true])->save();
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['person', $this->awa->id]]);
    expect($proposition->person_id)->toBe($this->awa->id)->and($proposition->visibility)->toBe('private');

    DB::table('person_blocks')->insert(['user_id' => $this->awa->id, 'blocked_id' => $this->yao->id, 'created_at' => now()]);
    expect(fn () => c1Proposition($this->yao, ['beneficiary' => ['person', $this->awa->id]]))->toThrow(ValidationException::class, 'ne reçoit pas');
});

it('n’enregistre jamais de paiement : une intention financière ne crée ni transaction, ni portefeuille, ni reçu, ni somme', function () {
    $avant = [Payment::count(), Contribution::count(), Allocation::count(), LedgerEntry::count(), Wallet::count()];
    $besoin = c1Besoin($this->awa, ['kind' => 'financial', 'title' => 'Parrainage scolaire']);
    $proposition = c1Proposition($this->moussa, ['need' => $besoin, 'kind' => 'financial', 'offer' => 'Je souhaite soutenir à hauteur de 10 000 FCFA.']);
    $this->service->accept($this->awa, $proposition);
    $this->service->declareDone($this->moussa, $proposition->fresh());
    $this->service->confirm($this->awa, $proposition->fresh());

    expect([Payment::count(), Contribution::count(), Allocation::count(), LedgerEntry::count(), Wallet::count()])->toBe($avant);
    expect(array_keys(DB::table('contribution_proposals')->first()->amount ?? []))->toBe([]);
    expect(Schema::hasColumn('contribution_proposals', 'amount'))->toBeFalse()
        ->and(Schema::hasColumn('contribution_proposals', 'payment_id'))->toBeFalse()
        ->and($proposition->fresh()->kind->disclaimer())->toContain('rien n’est payé');
});

it('refuse l’intention financière hors des cas prévus : besoin non financier, personne hors besoin', function () {
    $besoin = c1Besoin($this->awa, ['kind' => 'skills']);
    expect(fn () => c1Proposition($this->moussa, ['need' => $besoin, 'kind' => 'financial']))->toThrow(ValidationException::class, 'ne demande pas');

    $this->awa->profile->forceFill(['accepts_contributions' => true])->save();
    expect(fn () => c1Proposition($this->moussa, ['beneficiary' => ['person', $this->awa->id], 'kind' => 'financial']))->toThrow(ValidationException::class, 'besoin qu’elle a publié');

    // Autorisée : GAMAD, une ZUMRA, un projet ; ou la réponse à un besoin financier.
    expect(c1Proposition($this->moussa, ['beneficiary' => ['gamad', null], 'kind' => 'financial'])->kind->value)->toBe('financial');
    expect(c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id], 'kind' => 'financial'])->kind->value)->toBe('financial');
});

it('ferme le module : aucune proposition ni décision pour qui n’y a pas accès', function () {
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id]]);
    c1Module('off');

    expect(fn () => c1Proposition($this->moussa, ['beneficiary' => ['gamad', null], 'kind' => 'time']))->toThrow(HttpException::class);
    expect(fn () => $this->service->accept($this->yao, $proposition))->toThrow(AuthorizationException::class);
    expect(ContributionProposal::count())->toBe(1);
});
