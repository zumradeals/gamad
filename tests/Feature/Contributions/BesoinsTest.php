<?php

use App\Enums\NeedState;
use App\Enums\PublicationType;
use App\Enums\ZumraRole;
use App\Models\ContributionProposal;
use App\Models\ProjectMember;
use App\Models\Publication;
use App\Models\User;
use App\Services\ContributionNeeds;
use App\Services\ContributionProposals;
use App\Services\Publications;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** C1 (D-207) : publier un besoin au nom d'une personne, d'une ZUMRA, d'un projet, d'une entreprise ou de GAMAD. */
beforeEach(function () {
    c1Module('public');
    c1ModuleZumra('public');
    $this->awa = person('Awa Traoré', ['Couture']);
    $this->yao = person('Yao Kouassi');
    $this->zumra = zumra($this->yao, 'Couture solidaire');
});

it('publie un besoin personnel en quatre questions, sans budget ni document, et le laisse ouvert', function () {
    $besoin = c1Besoin($this->awa, ['title' => 'Machine à coudre', 'kind' => 'material', 'details' => '', 'needed_by' => now('Africa/Abidjan')->addMonth()->toDateString(), 'skills' => ['Couture']]);

    expect($besoin->type)->toBe(PublicationType::Need)
        ->and($besoin->need_state)->toBe(NeedState::Open)
        ->and($besoin->title)->toBe('Machine à coudre')
        ->and($besoin->help_kind)->toBe('material')
        // Sans précisions, le titre tient lieu de texte publié.
        ->and($besoin->body)->toBe('Machine à coudre')
        ->and($besoin->official)->toBeFalse()
        ->and($besoin->zumra_id)->toBeNull()->and($besoin->project_id)->toBeNull()->and($besoin->company_id)->toBeNull()
        ->and($besoin->skills->pluck('name')->all())->toBe(['Couture'])
        ->and($besoin->isManagedBy($this->awa))->toBeTrue()
        ->and($besoin->isManagedBy(person('Étrangère')))->toBeFalse();
});

it('valide le titre, la forme d’aide, les précisions, l’échéance et l’audience, avec des messages clairs', function () {
    $cas = [
        'title' => ['title' => 'ab'],
        'kind' => ['kind' => 'inconnue'],
        'details' => ['details' => str_repeat('a', 501)],
        'needed_by' => ['needed_by' => now('Africa/Abidjan')->subDay()->toDateString()],
        'visibility' => ['visibility' => 'zumra'],
        'skills' => ['skills' => ['A', 'B', 'C', 'D']],
    ];

    foreach ($cas as $champ => $donnees) {
        try {
            c1Besoin($this->awa, $donnees);
            $this->fail("Le champ $champ aurait dû être refusé.");
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($champ);
        }
    }

    expect(Publication::query()->whereNotNull('help_kind')->count())->toBe(0);
});

it('réserve le besoin d’une ZUMRA à ses responsables et accepte le niveau « membres de la ZUMRA »', function () {
    $besoin = c1Besoin($this->yao, ['visibility' => 'zumra'], ['zumra', $this->zumra->id]);

    expect($besoin->zumra_id)->toBe($this->zumra->id)->and($besoin->visibility)->toBe('zumra');

    $membre = person('Simple Membre');
    ajouteMembre($this->zumra, $membre);
    $responsable = person('Responsable');
    ajouteMembre($this->zumra, $responsable, ZumraRole::Manager);

    expect(fn () => c1Besoin($membre, [], ['zumra', $this->zumra->id]))->toThrow(HttpException::class);
    expect(fn () => c1Besoin($this->awa, [], ['zumra', $this->zumra->id]))->toThrow(HttpException::class);
    expect(c1Besoin($responsable, [], ['zumra', $this->zumra->id])->isManagedBy($responsable))->toBeTrue();
    expect($besoin->fresh()->isManagedBy($membre))->toBeFalse();
});

it('ne publie pas pour une ZUMRA quand le module ZUMRA est fermé', function () {
    c1ModuleZumra('off');

    expect(fn () => c1Besoin($this->yao, [], ['zumra', $this->zumra->id]))->toThrow(HttpException::class);
});

it('réserve le besoin d’un projet à son porteur ou à la personne qu’il a habilitée, jamais à la ZUMRA d’accueil', function () {
    $porteuse = person('Fatou Porteuse');
    $projet = c1Projet($porteuse, $this->zumra);
    $collaborateur = person('Collaborateur');
    ajouteMembre($this->zumra, $collaborateur);
    $member = new ProjectMember(['manages_contributions' => false]);
    $member->project()->associate($projet);
    $member->user()->associate($collaborateur);
    $member->save();

    $besoin = c1Besoin($porteuse, ['kind' => 'skills', 'visibility' => 'public'], ['project', $projet->id]);

    expect($besoin->project_id)->toBe($projet->id)
        // Le besoin d'un projet porte aussi la ZUMRA d'accueil, sans lui donner de pouvoir sur lui.
        ->and($besoin->zumra_id)->toBe($this->zumra->id)
        ->and($besoin->isManagedBy($porteuse))->toBeTrue()
        ->and($besoin->isManagedBy($this->yao))->toBeFalse()
        ->and($besoin->isManagedBy($collaborateur))->toBeFalse();

    // Le collaborateur non habilité, le fondateur de la ZUMRA d'accueil et une personne extérieure sont refusés.
    foreach ([$collaborateur, $this->yao, $this->awa] as $intrus) {
        expect(fn () => c1Besoin($intrus, [], ['project', $projet->id]))->toThrow(HttpException::class);
    }

    // Une fois habilité par le porteur, le collaborateur publie et gère.
    $member->forceFill(['manages_contributions' => true])->save();
    $deuxieme = c1Besoin($collaborateur, ['title' => 'Un formateur'], ['project', $projet->id]);
    expect($deuxieme->isManagedBy($collaborateur))->toBeTrue()->and($deuxieme->isManagedBy($porteuse))->toBeTrue()->and($deuxieme->isManagedBy($this->yao))->toBeFalse();
});

it('refuse le besoin d’un projet resté privé, par respect de la confidentialité du lot P1', function () {
    $porteuse = person('Fatou Porteuse');
    $projet = c1Projet($porteuse, $this->zumra, 'Projet discret', publie: false);

    expect(fn () => c1Besoin($porteuse, [], ['project', $projet->id]))->toThrow(ValidationException::class, 'privé');
});

it('réserve le besoin d’une entreprise à son gestionnaire, en public et jamais en financier', function () {
    $patron = person('Patron Entreprise');
    $entreprise = c1Entreprise($patron);

    $besoin = c1Besoin($patron, ['kind' => 'skills'], ['company', $entreprise->id]);
    expect($besoin->company_id)->toBe($entreprise->id)->and($besoin->visibility)->toBe('public')->and($besoin->isManagedBy($patron))->toBeTrue();

    expect(fn () => c1Besoin($this->awa, [], ['company', $entreprise->id]))->toThrow(HttpException::class);
    expect(fn () => c1Besoin($patron, ['kind' => 'financial'], ['company', $entreprise->id]))->toThrow(ValidationException::class, 'financier');
    expect(fn () => c1Besoin($patron, ['visibility' => 'members'], ['company', $entreprise->id]))->toThrow(ValidationException::class);
});

it('réserve le besoin officiel de GAMAD au super administrateur', function () {
    $admin = User::factory()->superAdmin()->create();

    $besoin = c1Besoin($admin, ['title' => 'Traduire la charte'], ['gamad', null]);
    expect($besoin->official)->toBeTrue()->and($besoin->isManagedBy($admin))->toBeTrue()->and($besoin->isManagedBy($this->awa))->toBeFalse();

    expect(fn () => c1Besoin($this->awa, [], ['gamad', null]))->toThrow(HttpException::class);
});

it('refuse un bénéficiaire inventé ou manipulé, et ferme tout le module hors accès', function () {
    expect(fn () => c1Besoin($this->awa, [], ['zumra', 'pas-un-identifiant']))->toThrow(ValidationException::class);
    expect(fn () => c1Besoin($this->awa, [], ['inconnu', null]))->toThrow(ValidationException::class);

    c1Module('off');
    expect(fn () => c1Besoin($this->awa))->toThrow(HttpException::class);
});

it('limite le nombre de besoins publiés par jour', function () {
    config(['gamad.limits.contribution_needs_per_day' => 2]);

    c1Besoin($this->awa);
    c1Besoin($this->awa);

    expect(fn () => c1Besoin($this->awa))->toThrow(ValidationException::class, 'limite');
});

it('suit le cycle publié, en cours de réponse, satisfait, et refuse ensuite toute proposition', function () {
    $besoin = c1Besoin($this->awa);
    $aidant = person('Moussa Koné');

    expect($besoin->need_state->contributionLabel())->toBe('Publié');

    $proposition = c1Proposition($aidant, ['need' => $besoin, 'kind' => 'material']);
    app(ContributionProposals::class)->accept($this->awa, $proposition);
    expect($besoin->fresh()->need_state->contributionLabel())->toBe('En cours de réponse');

    app(Publications::class)->resolve($this->awa, $besoin->fresh());
    expect($besoin->fresh()->need_state->contributionLabel())->toBe('Satisfait');

    expect(fn () => c1Proposition(person('Autre Aidant'), ['need' => $besoin->fresh(), 'kind' => 'material']))->toThrow(ValidationException::class, 'n’accepte plus');
    expect(Gate::forUser(person('Troisième'))->allows('contribute', $besoin->fresh()))->toBeFalse();
});

it('refuse d’office les propositions en attente quand le besoin est clôturé, sans toucher à celles déjà acceptées', function () {
    $besoin = c1Besoin($this->awa);
    $attente = c1Proposition(person('Aidant Un'), ['need' => $besoin, 'kind' => 'material']);
    $acceptee = c1Proposition(person('Aidant Deux'), ['need' => $besoin, 'kind' => 'material']);
    app(ContributionProposals::class)->accept($this->awa, $acceptee);

    app(Publications::class)->close($this->awa, $besoin->fresh());

    expect($besoin->fresh()->need_state)->toBe(NeedState::Closed)
        ->and($attente->fresh()->state->value)->toBe('declined')
        ->and($attente->fresh()->decision_note)->toBe('Ce besoin est clos.')
        ->and($acceptee->fresh()->state->value)->toBe('accepted');

    expect(fn () => app(ContributionProposals::class)->accept($this->awa, $attente->fresh()))->toThrow(AuthorizationException::class);
    expect(ContributionProposal::query()->where('need_id', $besoin->id)->count())->toBe(2);
});

it('réserve la clôture et la satisfaction d’un besoin à celui qui le gère', function () {
    $besoin = c1Besoin($this->awa);

    expect(fn () => app(Publications::class)->close(person('Intruse'), $besoin))->toThrow(AuthorizationException::class);
    expect(fn () => app(Publications::class)->resolve($this->yao, $besoin))->toThrow(AuthorizationException::class);
});

it('garde les besoins publiés avant C1 intacts : sans titre ni forme d’aide, ils restent des besoins du Fil', function () {
    $ancien = publication($this->awa, PublicationType::Need, ['body' => 'Besoin de l’ancien parcours.'])->fresh();

    expect($ancien->title)->toBeNull()->and($ancien->help_kind)->toBeNull()->and($ancien->official)->toBeFalse()
        ->and(app(ContributionNeeds::class))->toBeInstanceOf(ContributionNeeds::class);
});
