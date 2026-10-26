<?php

use App\Enums\NotificationKind;
use App\Enums\ProjectHostingState;
use App\Enums\ZumraRole;
use App\Livewire\MesProjets;
use App\Livewire\MondeProjet;
use App\Livewire\ProjetVitrine;
use App\Models\Company;
use App\Models\ParticipationRequest;
use App\Models\Project;
use App\Models\ProjectCompanyLink;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ParticipationRequests;
use App\Services\ProjectCompanyLinks;
use App\Services\ProjectHosting;
use App\Services\ProjectInvitations;
use App\Services\Projects;
use App\Services\ProjectShowcase;
use App\Services\ProjectSteps;
use App\Services\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** P1 (D-206) : accueil par une ZUMRA, présentation publique limitée, confidentialité, liens avec une entreprise. */
beforeEach(function () {
    $this->yao = person('Yao Kouassi', ['Couture']);
    $this->zumra = zumra($this->yao, 'Couture solidaire');
    $this->resp = person('Responsable Un', []);
    $this->membre = person('Simple Membre', []);
    $this->porteuse = person('Fatou Porteuse', ['Gestion']);
    $this->autre = person('Personne Extérieure', []);
    ajouteMembre($this->zumra, $this->resp, ZumraRole::Manager);
    ajouteMembre($this->zumra, $this->membre);
    // Un projet de Fatou, qui n'est pas membre de la ZUMRA : l'accueil est demandé.
    $this->projet = app(Projects::class)->create($this->porteuse, $this->zumra, ['name' => 'Académie numérique', 'goal' => 'But détaillé et confidentiel du projet.', 'hosting_message' => 'Nous cherchons un accueil.']);
});

function ouvre(User $qui, Project $projet)
{
    return Livewire::actingAs($qui)->test(MondeProjet::class, ['project' => $projet]);
}

// ─── Accueil par une ZUMRA ───────────────────────────────────────────────────

it('garde en brouillon privé un projet proposé à une ZUMRA, sans le rattacher tant que l’accueil n’est pas accepté', function () {
    expect($this->projet->hosting_state)->toBe(ProjectHostingState::Pending)->and($this->projet->isHosted())->toBeFalse()->and($this->projet->origin_zumra_id)->toBeNull();
    expect(UserNotification::query()->where('user_id', $this->yao->id)->where('kind', NotificationKind::ProjectHostingAsked->value)->count())->toBe(1)
        ->and(UserNotification::query()->where('user_id', $this->resp->id)->where('kind', NotificationKind::ProjectHostingAsked->value)->count())->toBe(1)
        ->and(UserNotification::query()->where('user_id', $this->membre->id)->count())->toBe(0);

    expect(fn () => app(ProjectShowcase::class)->publish($this->porteuse, $this->projet, ['summary' => 'Résumé']))->toThrow(ValidationException::class, 'doit d’abord accepter');
});

it('laisse le fondateur et les responsables accepter l’accueil, sans rien transférer, et informe le porteur', function () {
    app(ProjectHosting::class)->accept($this->resp, $this->projet);

    $p = $this->projet->fresh();
    expect($p->hosting_state)->toBe(ProjectHostingState::Accepted)->and($p->isHosted())->toBeTrue()->and($p->hosting_decided_by)->toBe($this->resp->id)
        ->and($p->origin_zumra_id)->toBe($this->zumra->id)->and($p->owner_id)->toBe($this->porteuse->id);
    expect($this->zumra->roleOf($this->porteuse))->toBeNull();
    expect(UserNotification::query()->where('user_id', $this->porteuse->id)->where('kind', NotificationKind::ProjectHostingAccepted->value)->count())->toBe(1);
    // La ZUMRA n'obtient aucun droit sur le projet.
    foreach ([$this->yao, $this->resp] as $qui) {
        expect(Gate::forUser($qui)->allows('view', $p))->toBeFalse()->and(Gate::forUser($qui)->allows('update', $p))->toBeFalse();
    }
});

it('refuse l’accueil, informe le porteur, qui garde son projet et peut en demander un autre', function () {
    app(ProjectHosting::class)->decline($this->yao, $this->projet);

    expect($this->projet->fresh()->hosting_state)->toBe(ProjectHostingState::Declined)->and($this->projet->fresh()->owner_id)->toBe($this->porteuse->id);
    expect(UserNotification::query()->where('user_id', $this->porteuse->id)->where('kind', NotificationKind::ProjectHostingDeclined->value)->count())->toBe(1);

    $autre = zumra($this->autre, 'Autre ZUMRA');
    app(ProjectHosting::class)->request($this->porteuse, $this->projet->fresh(), $autre, 'Et chez vous ?');
    expect($this->projet->fresh()->zumra_id)->toBe($autre->id)->and($this->projet->fresh()->hosting_state)->toBe(ProjectHostingState::Pending);
});

it('réserve la décision d’accueil aux responsables de la ZUMRA demandée, une seule fois', function () {
    foreach ([$this->membre, $this->porteuse, $this->autre] as $qui) {
        expect(fn () => app(ProjectHosting::class)->accept($qui, $this->projet))->toThrow(AuthorizationException::class);
        expect(fn () => app(ProjectHosting::class)->decline($qui, $this->projet))->toThrow(AuthorizationException::class);
    }
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    expect(fn () => app(ProjectHosting::class)->decline($this->resp, $this->projet->fresh()))->toThrow(ValidationException::class);
});

it('laisse le porteur retirer sa demande, puis la refaire, et refuse de retirer une demande déjà traitée', function () {
    app(ProjectHosting::class)->withdraw($this->porteuse, $this->projet);
    expect($this->projet->fresh()->hosting_state)->toBe(ProjectHostingState::Withdrawn);
    expect(fn () => app(ProjectHosting::class)->accept($this->yao, $this->projet->fresh()))->toThrow(ValidationException::class);

    app(ProjectHosting::class)->request($this->porteuse, $this->projet->fresh(), $this->zumra);
    expect($this->projet->fresh()->hosting_state)->toBe(ProjectHostingState::Pending);
    app(ProjectHosting::class)->accept($this->yao, $this->projet->fresh());
    expect(fn () => app(ProjectHosting::class)->withdraw($this->porteuse, $this->projet->fresh()))->toThrow(ValidationException::class);
    expect(fn () => app(ProjectHosting::class)->withdraw($this->autre, $this->projet->fresh()))->toThrow(AuthorizationException::class);
});

it('conserve la ZUMRA d’origine quand le projet change de ZUMRA, et retire alors la présentation publique', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    app(ProjectShowcase::class)->publish($this->porteuse, $this->projet->fresh(), ['summary' => 'Résumé public.']);
    $ailleurs = zumra($this->autre, 'Ailleurs');
    $ailleurs->memberships()->count();

    app(ProjectHosting::class)->request($this->porteuse, $this->projet->fresh(), $ailleurs);

    $p = $this->projet->fresh();
    expect($p->origin_zumra_id)->toBe($this->zumra->id)->and($p->zumra_id)->toBe($ailleurs->id)->and($p->showcase_published_at)->toBeNull()->and($p->hosting_state)->toBe(ProjectHostingState::Pending);
});

it('montre aux responsables seulement la demande d’accueil, sans le dossier, et la traite depuis la ZUMRA', function () {
    $c = monde($this->resp, $this->zumra, 'projets')->assertSeeText('Demandes d’accueil de projets')->assertSeeText('Académie numérique')->assertSeeText('But détaillé et confidentiel du projet.')->assertSeeText('Fatou Porteuse')->assertSeeText('Nous cherchons un accueil.');
    $c->call('acceptHosting', $this->projet->id);
    expect($this->projet->fresh()->isHosted())->toBeTrue();

    $this->projet->forceFill(['hosting_state' => ProjectHostingState::Pending])->save();
    monde($this->membre, $this->zumra, 'projets')->assertDontSeeText('Demandes d’accueil')->assertDontSeeText('Académie numérique');
    $this->projet->forceFill(['hosting_state' => ProjectHostingState::Accepted])->save();
    monde($this->membre, $this->zumra, 'projets')->assertDontSeeText('Académie numérique');
});

it('refuse à un non-responsable de décider depuis l’écran de la ZUMRA, et ignore un projet d’une autre ZUMRA', function () {
    monde($this->membre, $this->zumra, 'projets')->call('acceptHosting', $this->projet->id)->assertNotFound();
    $ailleurs = zumra($this->autre, 'Ailleurs');
    $ici = projet($this->autre, $ailleurs, 'Projet ailleurs');
    expect(fn () => monde($this->yao, $this->zumra, 'projets')->call('acceptHosting', $ici->id))->toThrow(ModelNotFoundException::class);
    expect($this->projet->fresh()->hosting_state)->toBe(ProjectHostingState::Pending);
});

// ─── Présentation publique limitée ───────────────────────────────────────────

it('publie seulement ce que le porteur a écrit, et jamais le dossier : but, équipe, étapes, publications', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    app(ProjectInvitations::class)->accept($this->membre, app(ProjectInvitations::class)->invite($this->porteuse, $this->projet->fresh(), $this->membre));
    app(ProjectSteps::class)->add($this->porteuse, $this->projet->fresh(), 'Étape secrète du plan');
    app(ProjectShowcase::class)->publish($this->porteuse, $this->projet->fresh(), ['summary' => 'Former des jeunes au numérique.', 'contribute' => 'Offrez du temps de mentorat.']);

    $page = $this->get(route('projets.show', $this->projet))->assertOk()->assertSeeText('Présentation publique')->assertSeeText('Former des jeunes au numérique.')
        ->assertSeeText('Offrez du temps de mentorat.')->assertSeeText('Couture solidaire')->assertSeeText('Une visibilité maîtrisée')
        ->assertDontSeeText('But détaillé et confidentiel')->assertDontSeeText('Étape secrète')->assertDontSeeText('Simple Membre')->assertDontSeeText('Fatou Porteuse')
        ->assertDontSeeText('Suivre le projet');
    expect($page->getContent())->not->toContain('Contacter le porteur');
    $this->get(route('projets.show', $this->projet))->assertSee(route('zumras.show', $this->zumra));
});

it('montre le porteur et les compétences seulement si le porteur le choisit', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    app(Projects::class)->setNeeds($this->porteuse, $this->projet->fresh(), ['Couture', 'Comptabilité']);
    app(ProjectShowcase::class)->publish($this->porteuse, $this->projet->fresh(), ['summary' => 'Résumé.', 'show_owner' => true, 'show_needs' => false]);

    $this->get(route('projets.show', $this->projet))->assertSeeText('Fatou Porteuse')->assertSeeText('Contacter le porteur')->assertDontSeeText('Comptabilité');
    app(ProjectShowcase::class)->publish($this->porteuse, $this->projet->fresh(), ['summary' => 'Résumé.', 'show_owner' => false, 'show_needs' => true]);
    $this->get(route('projets.show', $this->projet))->assertDontSeeText('Fatou Porteuse')->assertSeeText('Comptabilité')->assertSeeText('Couture');
});

it('retire la présentation publique aussitôt, et la garde pour une republication', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    app(ProjectShowcase::class)->publish($this->porteuse, $this->projet->fresh(), ['summary' => 'Résumé public.']);
    $this->get(route('projets.show', $this->projet))->assertOk();

    app(ProjectShowcase::class)->unpublish($this->porteuse, $this->projet->fresh());

    $this->get(route('projets.show', $this->projet))->assertNotFound();
    $this->actingAs($this->membre)->get(route('projets.show', $this->projet))->assertNotFound();
    expect($this->projet->fresh()->showcase_summary)->toBe('Résumé public.')->and($this->projet->fresh()->isShowcasePublished())->toBeFalse();
    $this->actingAs($this->porteuse)->get(route('projets.show', $this->projet))->assertOk()->assertSeeText('Projet privé');
});

it('vérifie le résumé (obligatoire, 300 caractères) et le texte « comment contribuer » (500 caractères)', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);

    foreach ([['summary' => ''], ['summary' => str_repeat('a', 301)], ['summary' => 'ok', 'contribute' => str_repeat('a', 501)]] as $data) {
        expect(fn () => app(ProjectShowcase::class)->publish($this->porteuse, $this->projet->fresh(), $data))->toThrow(ValidationException::class);
    }
    expect($this->projet->fresh()->showcase_published_at)->toBeNull();
});

it('réserve la publication et le retrait au porteur : ni la ZUMRA d’accueil, ni un collaborateur', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    app(ProjectInvitations::class)->accept($this->membre, app(ProjectInvitations::class)->invite($this->porteuse, $this->projet->fresh(), $this->membre));
    app(ProjectShowcase::class)->publish($this->porteuse, $this->projet->fresh(), ['summary' => 'Résumé public.']);

    foreach ([$this->yao, $this->resp, $this->membre, $this->autre] as $qui) {
        expect(fn () => app(ProjectShowcase::class)->publish($qui, $this->projet->fresh(), ['summary' => 'Intrus']))->toThrow(AuthorizationException::class);
        expect(fn () => app(ProjectShowcase::class)->unpublish($qui, $this->projet->fresh()))->toThrow(AuthorizationException::class);
    }
    expect($this->projet->fresh()->showcase_summary)->toBe('Résumé public.');
});

it('publie et retire depuis le dossier du porteur, avec la liste de ce qui est public et privé', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);

    ouvre($this->porteuse, $this->projet)->set('tab', 'vitrine')->assertSeeText('Toujours privé')->assertSeeText('Pour l’instant, personne ne peut voir ce projet')
        ->set('showSummary', '')->call('publishShowcase')->assertHasErrors('summary')
        ->set('showSummary', 'Un résumé clair.')->call('publishShowcase')->assertHasNoErrors()->assertSeeText('Présentation publique publiée.')
        ->call('unpublishShowcase')->assertSeeText('Présentation publique retirée.');
    expect($this->projet->fresh()->isShowcasePublished())->toBeFalse();
});

it('n’affiche pas la présentation d’un projet non accueilli, même publiée avant un changement de ZUMRA', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    app(ProjectShowcase::class)->publish($this->porteuse, $this->projet->fresh(), ['summary' => 'Résumé public.']);
    $this->projet->forceFill(['hosting_state' => ProjectHostingState::Withdrawn])->save();

    $this->get(route('projets.show', $this->projet))->assertNotFound();
    expect(Project::query()->showcased()->count())->toBe(0);
});

it('laisse un visiteur lire la présentation quand le module est public, et la ferme quand il est désactivé', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    app(ProjectShowcase::class)->publish($this->porteuse, $this->projet->fresh(), ['summary' => 'Résumé public.']);
    $admin = User::factory()->superAdmin()->create();
    config(['gamad.initial_experience' => true]);

    app(Settings::class)->update(['zumra_module' => 'off'], $admin);
    $this->get(route('projets.show', $this->projet))->assertNotFound();
    $this->actingAs($this->porteuse)->get(route('projets.show', $this->projet))->assertNotFound();

    app(Settings::class)->update(['zumra_module' => 'test'], $admin);
    app('auth')->forgetGuards();
    $this->get(route('projets.show', $this->projet))->assertNotFound();
    $this->actingAs($admin)->get(route('projets.show', $this->projet))->assertOk();

    app(Settings::class)->update(['zumra_module' => 'public'], $admin);
    app('auth')->forgetGuards();
    $this->get(route('projets.show', $this->projet))->assertOk()->assertSeeText('Résumé public.');
});

// ─── Collaborateurs ──────────────────────────────────────────────────────────

it('donne au collaborateur la lecture du dossier sans pouvoir le modifier', function () {
    app(ProjectInvitations::class)->accept($this->membre, app(ProjectInvitations::class)->invite($this->porteuse, $this->projet, $this->membre));

    expect(Gate::forUser($this->membre)->allows('view', $this->projet))->toBeTrue()->and(Gate::forUser($this->membre)->allows('update', $this->projet))->toBeFalse();
    ouvre($this->membre, $this->projet)->assertSeeText('Projet partagé')->assertDontSeeText('Modifier le projet')->assertDontSeeText('Nouvelle étape');
    $this->actingAs($this->membre)->get(route('projets.edit', $this->projet))->assertNotFound();
    ouvre($this->membre, $this->projet)->call('changeState', 'in_progress')->assertNotFound();
});

it('ne laisse aucun collaborateur d’un autre projet ouvrir celui-ci', function () {
    $autre = projet($this->autre, zumra($this->autre, 'Autre ZUMRA'), 'Projet ailleurs');
    app(ProjectInvitations::class)->accept($this->membre, app(ProjectInvitations::class)->invite($this->autre, $autre, $this->membre));

    $this->actingAs($this->membre)->get(route('projets.show', $autre))->assertOk();
    $this->actingAs($this->membre)->get(route('projets.show', $this->projet))->assertNotFound();
});

// ─── Mes projets ─────────────────────────────────────────────────────────────

it('regroupe dans Mes projets mes projets, mes collaborations, mes invitations et mes demandes', function () {
    $mien = projet($this->membre, $this->zumra, 'Mon petit projet');
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    app(ProjectShowcase::class)->publish($this->porteuse, $this->projet->fresh(), ['summary' => 'Résumé public.']);
    app(ParticipationRequests::class)->propose($this->membre, $this->projet->fresh());
    $invitee = person('Invitée', []);
    app(ProjectInvitations::class)->invite($this->membre, $mien, $invitee);

    Livewire::actingAs($this->membre)->test(MesProjets::class)->assertSeeText('Mon petit projet')->assertSeeText('Projet privé')->assertSeeText('Mes demandes d’aide en attente')->assertSeeText('Académie numérique')->assertDontSeeText('Invitations à collaborer');
    Livewire::actingAs($invitee)->test(MesProjets::class)->assertSeeText('Invitations à collaborer')->assertSeeText('Mon petit projet');
    Livewire::actingAs($this->porteuse)->test(MesProjets::class)->assertSeeText('Académie numérique')->assertSeeText('Accueilli par Couture solidaire')->assertSeeText('Présentation publique')->assertDontSeeText('Mon petit projet');
    $this->actingAs($this->porteuse)->get(route('espace'))->assertSee(route('espace.projets'), false);
});

it('ne crée aucun menu Projets : ni dans la barre d’ordinateur, ni sur téléphone', function () {
    config(['gamad.initial_experience' => true]);
    app(Settings::class)->update(['zumra_module' => 'public'], User::factory()->superAdmin()->create());
    $html = $this->actingAs($this->porteuse)->get(route('fil'))->getContent();

    expect($html)->not->toContain('href="'.route('projets.index').'"')->not->toContain('>Projets<');
    preg_match_all('/<nav aria-label="Navigation principale".*?<\/nav>/s', $html, $navs);
    expect(implode(' ', $navs[0]))->not->toContain('Projets');
});

// ─── Entreprise ──────────────────────────────────────────────────────────────

function entreprise_verifiee(User $proprietaire, string $nom = 'Atelier Soleil'): Company
{
    $company = new Company(['name' => $nom, 'legal_name' => $nom.' SARL', 'registration_country' => 'CI', 'registration_number' => Str::random(12), 'sector' => 'Services', 'city' => 'Abidjan']);
    $company->slug = Str::slug($nom).'-'.strtolower(Str::random(8));
    $company->forceFill(['owner_id' => $proprietaire->id, 'status' => Company::VERIFIED, 'verified_at' => now()])->save();

    return $company;
}

it('relie un projet à une entreprise vérifiée seulement avec son accord, en gardant la ZUMRA d’origine', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    $patron = person('Patron Entreprise', []);
    $entreprise = entreprise_verifiee($patron);

    $lien = app(ProjectCompanyLinks::class)->propose($this->porteuse, $this->projet->fresh(), $entreprise, 'supported');
    expect($lien->state)->toBe(ProjectCompanyLink::REQUESTED)->and($lien->origin_zumra_id)->toBe($this->zumra->id);
    expect(UserNotification::query()->where('user_id', $patron->id)->where('kind', NotificationKind::ProjectCompanyAsked->value)->count())->toBe(1);

    foreach ([$this->yao, $this->porteuse, $this->autre] as $intrus) {
        expect(fn () => app(ProjectCompanyLinks::class)->confirm($intrus, $lien))->toThrow(HttpException::class);
    }
    app(ProjectCompanyLinks::class)->confirm($patron, $lien);
    expect($lien->fresh()->state)->toBe(ProjectCompanyLink::CONFIRMED)->and($this->projet->fresh()->zumra_id)->toBe($this->zumra->id);
    expect(UserNotification::query()->where('user_id', $this->porteuse->id)->where('kind', NotificationKind::ProjectCompanyAnswered->value)->count())->toBe(1);
});

it('réserve la demande de lien au porteur, refuse une entreprise non vérifiée et un doublon, et permet d’y mettre fin', function () {
    $patron = person('Patron Entreprise', []);
    $entreprise = entreprise_verifiee($patron);

    expect(fn () => app(ProjectCompanyLinks::class)->propose($this->yao, $this->projet, $entreprise, 'carried'))->toThrow(AuthorizationException::class);
    expect(fn () => app(ProjectCompanyLinks::class)->propose($this->porteuse, $this->projet, $entreprise, 'inconnu'))->toThrow(ValidationException::class);
    $entreprise->forceFill(['status' => 'pending'])->save();
    expect(fn () => app(ProjectCompanyLinks::class)->propose($this->porteuse, $this->projet, $entreprise->fresh(), 'carried'))->toThrow(ValidationException::class);

    $entreprise->forceFill(['status' => Company::VERIFIED])->save();
    $lien = app(ProjectCompanyLinks::class)->propose($this->porteuse, $this->projet, $entreprise->fresh(), 'carried');
    expect(fn () => app(ProjectCompanyLinks::class)->propose($this->porteuse, $this->projet, $entreprise->fresh(), 'carried'))->toThrow(ValidationException::class);

    app(ProjectCompanyLinks::class)->decline($patron, $lien);
    expect($lien->fresh()->state)->toBe(ProjectCompanyLink::DECLINED);
    $nouveau = app(ProjectCompanyLinks::class)->propose($this->porteuse, $this->projet, $entreprise->fresh(), 'carried');
    app(ProjectCompanyLinks::class)->confirm($patron, $nouveau);
    app(ProjectCompanyLinks::class)->end($this->porteuse, $nouveau);
    expect($nouveau->fresh()->state)->toBe(ProjectCompanyLink::ENDED)->and($nouveau->fresh()->ended_at)->not->toBeNull();
});

it('montre le projet sur la page de l’entreprise seulement s’il est présenté publiquement et le lien confirmé', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    $patron = person('Patron Entreprise', []);
    $entreprise = entreprise_verifiee($patron);
    $lien = app(ProjectCompanyLinks::class)->propose($this->porteuse, $this->projet->fresh(), $entreprise, 'carried');
    config(['gamad.initial_experience' => true]);
    app(Settings::class)->update(['zumra_module' => 'public'], User::factory()->superAdmin()->create());

    $this->get(route('entreprises.show', $entreprise))->assertOk()->assertDontSeeText('Académie numérique');
    app(ProjectCompanyLinks::class)->confirm($patron, $lien);
    $this->get(route('entreprises.show', $entreprise))->assertOk()->assertDontSeeText('Académie numérique');
    app(ProjectShowcase::class)->publish($this->porteuse, $this->projet->fresh(), ['summary' => 'Résumé public.']);
    $this->get(route('entreprises.show', $entreprise))->assertOk()->assertSeeText('Projets')->assertSeeText('Académie numérique')->assertSeeText('Porté par Atelier Soleil');

    app(Settings::class)->update(['zumra_module' => 'off'], User::factory()->superAdmin()->create());
    $this->get(route('entreprises.show', $entreprise))->assertOk()->assertDontSeeText('Académie numérique');
});

it('propose et confirme le lien depuis les écrans', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    $patron = person('Patron Entreprise', []);
    $entreprise = entreprise_verifiee($patron);

    ouvre($this->porteuse, $this->projet)->set('tab', 'entreprise')->set('companyId', $entreprise->id)->set('companyKind', 'carried')->call('proposeCompany')->assertSeeText('Demande envoyée à l’entreprise')->assertSeeText('En attente de confirmation');
    $lien = ProjectCompanyLink::sole();
    Livewire::actingAs($patron)->test(MesProjets::class)->assertSeeText('Projets à relier à votre entreprise')->assertSeeText('Académie numérique')->call('confirmLink', $lien->id)->assertSeeText('Lien confirmé.');
    expect($lien->fresh()->state)->toBe(ProjectCompanyLink::CONFIRMED);
});

// ─── Compétences et étapes ───────────────────────────────────────────────────

it('range les compétences recherchées dans le référentiel commun, huit au plus', function () {
    app(Projects::class)->setNeeds($this->porteuse, $this->projet, ['Couture', 'couture ', 'Comptabilité']);
    expect($this->projet->neededSkills()->pluck('name')->all())->toBe(['Couture', 'Comptabilité'])->and(Skill::query()->where('normalized', 'couture')->count())->toBe(1);

    expect(fn () => app(Projects::class)->setNeeds($this->porteuse, $this->projet, array_map(fn ($i) => "Compétence $i", range(1, 9))))->toThrow(ValidationException::class);
    expect(fn () => app(Projects::class)->setNeeds($this->yao, $this->projet, ['Intrus']))->toThrow(AuthorizationException::class);
    expect($this->projet->neededSkills()->count())->toBe(2);
});

it('affiche le dossier privé : état, étape, ZUMRA d’accueil, et son accès en trois tons de confidentialité', function () {
    $c = ouvre($this->porteuse, $this->projet)->assertSeeText('Projet privé')->assertSeeText('Accueil demandé : Couture solidaire')->assertSeeText('Étape de vie')->assertSeeText('Idée');
    $c->call('setStage', 'development')->assertSeeText('Étape du projet mise à jour.');
    expect($this->projet->fresh()->stage->value)->toBe('development');
    $c->call('setStage', 'inconnue')->assertNotFound();
});

it('demande et retire l’accueil depuis le dossier, en nommant la ZUMRA', function () {
    $autre = zumra($this->autre, 'Autre ZUMRA');

    ouvre($this->porteuse, $this->projet)->set('tab', 'accueil')->assertSeeText('Accueil demandé')->call('withdrawHosting')->assertSeeText('Demande d’accueil retirée.')
        ->set('hostZumraId', $autre->id)->set('hostMessage', 'Chez vous ?')->call('requestHosting')->assertSeeText('Demande d’accueil envoyée');
    expect($this->projet->fresh()->zumra_id)->toBe($autre->id)->and($this->projet->fresh()->hosting_message)->toBe('Chez vous ?');
});

it('ne touche pas à un identifiant de ZUMRA inconnu', function () {
    ouvre($this->porteuse, $this->projet)->set('tab', 'accueil')->set('hostZumraId', 'pas-un-uuid')->call('requestHosting')->assertNotFound();
});

it('garde la page de la ZUMRA lisible quand le projet accueilli est privé : seul son compte et sa trace subsistent', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);

    monde($this->resp, $this->zumra, 'projets')->assertDontSeeText('Académie numérique')->assertDontSeeText('But détaillé');
    monde($this->resp, $this->zumra, 'formation')->assertSeeText('0')->assertSeeText('projet visible');
});

it('vérifie le droit de voir la présentation côté serveur, y compris dans une action Livewire', function () {
    app(ProjectHosting::class)->accept($this->yao, $this->projet);
    app(ProjectShowcase::class)->publish($this->porteuse, $this->projet->fresh(), ['summary' => 'Résumé public.']);

    $c = Livewire::actingAs($this->autre)->test(ProjetVitrine::class, ['project' => $this->projet->fresh()])->assertSeeText('Proposer mon aide');
    app(ProjectShowcase::class)->unpublish($this->porteuse, $this->projet->fresh());
    expect(fn () => $c->set('participationWord', 'Mot')->call('requestParticipation'))->toThrow(Exception::class);
    expect(ParticipationRequest::count())->toBe(0);
});
