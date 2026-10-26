<?php

use App\Enums\ZumraDuty;
use App\Enums\ZumraRole;
use App\Livewire\CarrefourZumras;
use App\Livewire\FormulaireZumra;
use App\Livewire\MesZumras;
use App\Livewire\ParametresZumra;
use App\Models\AdminAction;
use App\Models\Domain;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Zumra;
use App\Services\Invitations;
use App\Services\JoinRequests;
use App\Services\Memberships;
use App\Services\Settings;
use App\Services\Todo;
use App\Services\Zumras;
use App\Support\ZumraModule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Z1 (D-205) : le module ZUMRA, ouvert ou fermé par le seul super administrateur, dans le parcours actif. */
function ouvreModule(string $etat, string $testeurs = ''): void
{
    app(Settings::class)->update(['zumra_module' => $etat, 'zumra_testers' => $testeurs], User::factory()->superAdmin()->create());
}

function zumraZ1(User $fondateur, string $nom = 'Couture solidaire', array $data = []): Zumra
{
    return zumra($fondateur, $nom, $data + ['sphere' => 'social']);
}

it('reste désactivé par défaut : invisible dans la navigation et inaccessible par adresse directe', function () {
    $fondateur = User::factory()->superAdmin()->create();
    expect(fn () => zumraZ1(User::factory()->create(), 'Autre'))->toThrow(AuthorizationException::class);
    ouvreModule('test');
    $zumra = zumraZ1($fondateur, 'Sous test');
    ouvreModule('off');

    $simple = User::factory()->create();
    $texte = $this->actingAs($simple)->get(route('fil'))->assertOk()->getContent();
    expect($texte)->not->toContain('href="'.route('zumras.index').'"');

    $this->get(route('zumras.index'))->assertRedirect(route('personnes.index'));
    $this->actingAs($simple)->get(route('zumras.index'))->assertRedirect(route('personnes.index'));
    $this->actingAs($simple)->get(route('zumras.show', $zumra))->assertNotFound();
    $this->actingAs($simple)->get(route('zumras.create'))->assertNotFound();
    $this->actingAs($simple)->get(route('espace.zumras'))->assertNotFound();
    Livewire::actingAs($simple)->test(CarrefourZumras::class)->assertNotFound();
});

it('ouvre le test privé aux seuls super administrateurs et comptes testeurs à l’adresse vérifiée', function () {
    $admin = User::factory()->superAdmin()->create();
    $testeur = User::factory()->create(['email' => 'Testeur@exemple.org']);
    $nonVerifie = User::factory()->unverified()->create(['email' => 'inconnu@exemple.org']);
    $simple = User::factory()->create();
    ouvreModule('test', "testeur@exemple.org\ninconnu@exemple.org");
    $zumra = zumraZ1($admin);

    $this->actingAs($admin)->get(route('zumras.index'))->assertOk()->assertSeeText('Des communautés qui font la différence');
    $this->actingAs($testeur)->get(route('zumras.index'))->assertOk()->assertSeeText('Couture solidaire');
    $this->actingAs($testeur)->get(route('zumras.show', $zumra))->assertOk();

    foreach ([$simple, $nonVerifie] as $personne) {
        $this->actingAs($personne)->get(route('zumras.index'))->assertRedirect(route('personnes.index'));
        $this->actingAs($personne)->get(route('zumras.show', $zumra))->assertNotFound();
        Livewire::actingAs($personne)->test(CarrefourZumras::class)->assertNotFound();
    }

    $this->get(route('zumras.index'))->assertRedirect(route('personnes.index'));
    $this->get(route('zumras.show', $zumra))->assertNotFound();
});

it('refuse les actions d’adhésion et d’invitation à qui n’a pas accès, même appelées directement', function () {
    $admin = User::factory()->superAdmin()->create();
    ouvreModule('test');
    $zumra = zumraZ1($admin);
    $simple = person('Aïcha');

    expect(fn () => app(Invitations::class)->invite($admin, $zumra, $simple))->not->toThrow(AuthorizationException::class);
    ouvreModule('off');
    $autre = person('Bakary');
    expect(fn () => app(Invitations::class)->invite($admin, $zumra, $autre))->toThrow(AuthorizationException::class);
    expect(fn () => app(JoinRequests::class)->propose($autre, $zumra))->toThrow(AuthorizationException::class);
});

it('rend les ZUMRA publiques à tous quand le module est public, sans activer les projets', function () {
    $admin = User::factory()->superAdmin()->create();
    ouvreModule('public');
    $zumra = zumraZ1($admin, 'Communauté ouverte');

    $this->get(route('zumras.index'))->assertOk()->assertSeeText('Communauté ouverte')->assertDontSeeText('Voir les projets');
    $this->get(route('zumras.show', $zumra))->assertOk()->assertSeeText('Demander à rejoindre')->assertSeeText('Responsabilités');
    // Les projets (P1) restent privés : la ZUMRA n'affiche que les présentations publiées.
    $this->get(route('zumras.show', ['zumra' => $zumra, 'onglet' => 'projets']))->assertOk()->assertSeeText('Aucun projet pour l’instant.');
    // Il n'y a toujours pas de Carrefour des projets dans le parcours actif.
    $this->get(route('projets.index'))->assertRedirect(route('personnes.index'));
});

it('affiche le menu ZUMRA et le choix Découvrir seulement quand le module est accessible', function () {
    $admin = User::factory()->superAdmin()->create();
    $simple = User::factory()->create();

    $ferme = $this->actingAs($admin)->get(route('fil'))->assertOk()->getContent();
    expect($ferme)->not->toContain('href="'.route('zumras.index').'"');

    ouvreModule('test');
    $admin = $admin->fresh();
    $ouvert = $this->actingAs($admin)->get(route('fil'))->getContent();
    expect($ouvert)->toContain('href="'.route('zumras.index').'"')->and($ouvert)->toContain('id="decouvrir-titre"');
    $simple = $this->actingAs($simple)->get(route('fil'))->getContent();
    expect($simple)->not->toContain('href="'.route('zumras.index').'"')->and($simple)->not->toContain('id="decouvrir-titre"');
});

it('garde quatre entrées dans la barre du téléphone : Accueil, Découvrir, Agir, Mon espace', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('fil'))->assertOk()->getContent();
    $barre = substr($html, strpos($html, 'fixed inset-x-0 bottom-0'));
    $barre = substr($barre, 0, strpos($barre, '</nav>'));
    preg_match_all('/<(?:a|button)\b[^>]*class="[^"]*text-\[11px\][^"]*"[^>]*>(.*?)<\/(?:a|button)>/s', $barre, $entrees);
    $libelles = array_map(fn ($html) => trim(strip_tags($html)), $entrees[1]);

    expect($libelles)->toBe(['Accueil', 'Découvrir', 'Agir', 'Mon espace']);
});

it('ne permet qu’au super administrateur de régler le module, et journalise chaque changement', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs(User::factory()->create())->get(route('espace.parametres.zumra'))->assertForbidden();
    Livewire::actingAs(User::factory()->create())->test(ParametresZumra::class)->assertForbidden();

    Livewire::actingAs($admin)->test(ParametresZumra::class)
        ->set('state', 'test')->set('testers', 'Alice@exemple.org, bob@exemple.org')->call('save')->assertHasNoErrors()->assertSee('Réglages du module ZUMRA enregistrés');

    expect(app(Settings::class)->get('zumra_module'))->toBe('test')
        ->and(ZumraModule::testers())->toBe(['alice@exemple.org', 'bob@exemple.org'])
        ->and(AdminAction::query()->where('action', 'setting.changed')->where('subject_id', 'zumra_module')->exists())->toBeTrue();

    Livewire::actingAs($admin)->test(ParametresZumra::class)->set('state', 'ouvert')->call('save')->assertHasErrors('state');
    Livewire::actingAs($admin)->test(ParametresZumra::class)->set('testers', 'pas une adresse')->call('save')->assertHasErrors('testers');
});

it('crée une ZUMRA seul, avec sa nature, comme responsable principal', function () {
    ouvreModule('public');
    $auteur = person('Fondatrice');

    Livewire::actingAs($auteur)->test(FormulaireZumra::class)
        ->set('name', 'Atelier de quartier')->set('domain_id', Domain::query()->value('id'))->set('format', 'hybrid')->set('mission', 'Apprendre et agir ensemble.')
        ->call('save')->assertHasErrors('sphere')
        ->set('sphere', 'religious')->call('save')->assertHasNoErrors();

    $zumra = Zumra::query()->where('name', 'Atelier de quartier')->firstOrFail();
    expect($zumra->sphere->value)->toBe('religious')->and($zumra->roleOf($auteur))->toBe(ZumraRole::Founder)->and($zumra->memberships()->count())->toBe(1);
});

it('filtre le Carrefour par nature et fonctionnement, et ne met à la une que des ZUMRA reconnues', function () {
    $admin = User::factory()->superAdmin()->create();
    ouvreModule('public');
    $sociale = zumraZ1($admin, 'Entraide sociale', ['format' => 'online']);
    $pro = zumra($admin, 'Métiers du numérique', ['sphere' => 'professional', 'format' => 'hybrid']);

    Livewire::test(CarrefourZumras::class)->assertDontSee('ZUMRA à la une')
        ->call('pick', 'sphere', 'professional')->assertSee('Métiers du numérique')->assertDontSee('Entraide sociale')
        ->call('pick', 'sphere', 'professional')->call('pick', 'format', 'online')->assertSee('Entraide sociale')->assertDontSee('Métiers du numérique');

    app(Zumras::class)->recognize($admin, $pro);
    Livewire::test(CarrefourZumras::class)->assertSee('ZUMRA à la une')->assertSee('Reconnue par GAMAD');
    Livewire::test(CarrefourZumras::class)->set('query', 'Entraide')->assertDontSee('ZUMRA à la une');
});

it('présente les cinq responsabilités, occupées, en attente ou à pourvoir, sans score', function () {
    $admin = User::factory()->superAdmin()->create();
    ouvreModule('public');
    $zumra = zumraZ1($admin);
    $adjointe = person('Adjointe');
    ajouteMembre($zumra, $adjointe, ZumraRole::Manager);

    $gouvernance = collect($zumra->governance());
    expect($gouvernance)->toHaveCount(5)->and($gouvernance->where('state', 'free')->count())->toBe(4)->and($gouvernance->first()['state'])->toBe('occupied');

    $this->get(route('zumras.show', $zumra))->assertSeeText('Responsable principal')->assertSeeText('À pourvoir');
});

it('liste mes ZUMRA et laisse accepter ou refuser une invitation', function () {
    $fondateur = person('Fondateur');
    ouvreModule('public');
    $zumra = zumraZ1($fondateur, 'Entraide du quartier');
    $autre = zumraZ1($fondateur, 'Chorale des voisins');
    $invitee = person('Invitée');
    app(Invitations::class)->invite($fondateur, $zumra, $invitee, 'Venez nous aider.');
    $invitationAutre = app(Invitations::class)->invite($fondateur, $autre, $invitee);

    $page = Livewire::actingAs($invitee)->test(MesZumras::class)->assertSee('Mes invitations')->assertSee('Venez nous aider.')->assertSee('Entraide du quartier');
    $invitation = Invitation::query()->where('user_id', $invitee->id)->where('zumra_id', $zumra->id)->firstOrFail();
    $page->call('accept', $invitation->id)->assertSee('Mes communautés')->assertSee('Ouvrir l’espace');
    $page->call('refuse', $invitationAutre->id);

    expect($zumra->roleOf($invitee))->toBe(ZumraRole::Member)->and($autre->roleOf($invitee))->toBeNull();
    $this->actingAs($fondateur)->get(route('espace'))->assertOk()->assertSee('href="'.route('espace.zumras').'"', false);
});

/** Z1 (D-205), précisions du Président : fermeture totale et acceptation explicite des responsabilités. */
it('ferme totalement le module à un membre d’une ZUMRA existante, par adresse, par action et par service, sans rien supprimer', function () {
    $admin = User::factory()->superAdmin()->create();
    ouvreModule('public');
    $zumra = zumraZ1($admin);
    $membre = person('Membre');
    $manager = person('Responsable');
    ajouteMembre($zumra, $membre);
    ajouteMembre($zumra, $manager, ZumraRole::Manager);
    ouvreModule('off');

    foreach ([$membre, $manager] as $personne) {
        $this->actingAs($personne)->get(route('zumras.show', $zumra))->assertNotFound();
        $this->actingAs($personne)->get(route('zumras.edit', $zumra))->assertNotFound();
        $this->actingAs($personne)->get(route('zumras.invite', $zumra))->assertNotFound();
        $this->actingAs($personne)->get(route('zumras.create'))->assertNotFound();
        $this->actingAs($personne)->get(route('espace.zumras'))->assertNotFound();
        $this->actingAs($personne)->get(route('zumras.index'))->assertRedirect(route('personnes.index'));
        $this->actingAs($personne)->get(route('espace'))->assertOk()->assertDontSee('href="'.route('espace.zumras').'"', false);
    }
    Livewire::actingAs($membre)->test(MondeZumra::class, ['zumra' => $zumra])->assertNotFound();
    expect(fn () => app(Memberships::class)->leave($membre, $zumra))->toThrow(AuthorizationException::class);
    expect(fn () => app(Memberships::class)->remove($manager, $zumra, ligne($zumra, $membre)))->toThrow(AuthorizationException::class);
    expect(fn () => app(Invitations::class)->invite($manager, $zumra, person('Autre')))->toThrow(AuthorizationException::class);

    // Les données sont conservées, et le module rouvert les retrouve.
    expect($zumra->fresh()->memberships()->count())->toBe(3);
    ouvreModule('public');
    $this->actingAs($membre)->get(route('zumras.show', $zumra))->assertOk();
});

it('ferme aussi un module en test privé aux membres non autorisés et refuse une invitation reçue', function () {
    $admin = User::factory()->superAdmin()->create();
    ouvreModule('public');
    $zumra = zumraZ1($admin);
    $invitee = person('Invitée');
    $invitation = app(Invitations::class)->invite($admin, $zumra, $invitee);
    ouvreModule('test');

    $this->actingAs($invitee)->get(route('zumras.show', $zumra))->assertNotFound();
    expect(fn () => app(Invitations::class)->accept($invitee, $invitation))->toThrow(NotFoundHttpException::class);
    expect(collect(app(Todo::class)->for($invitee))->pluck('key')->contains('invitation-'.$invitation->id))->toBeFalse();
    expect($zumra->roleOf($invitee))->toBeNull();
});

function responsableNomme(Zumra $zumra, string $nom = 'Adjointe'): User
{
    $personne = person($nom);
    ajouteMembre($zumra, $personne, ZumraRole::Manager);

    return $personne;
}

it('propose un poste en attente, sans le rendre effectif, et informe la personne', function () {
    $fondateur = person('Fondatrice');
    ouvreModule('public');
    $zumra = zumraZ1($fondateur);
    $adjointe = responsableNomme($zumra);

    app(Memberships::class)->proposeDuty($fondateur, $zumra, ligne($zumra, $adjointe), ZumraDuty::Finance);

    $ligne = ligne($zumra, $adjointe);
    expect($ligne->dutyPending())->toBeTrue()->and($ligne->dutyEffective())->toBeFalse();
    $poste = collect($zumra->governance())->firstWhere('label', 'Responsable financier');
    expect($poste['state'])->toBe('pending')->and($poste['holder']->is($adjointe))->toBeTrue();
    expect(UserNotification::query()->where('user_id', $adjointe->id)->where('kind', 'zumra_duty_proposed')->exists())->toBeTrue();

    $page = $this->actingAs($fondateur)->get(route('zumras.show', $zumra))->assertOk();
    $page->assertSeeText('En attente : Adjointe')->assertDontSeeText('Poste occupé');
    // Un visiteur ne voit pas le nom, seulement qu'un poste est en attente.
    auth()->logout();
    $this->get(route('zumras.show', $zumra))->assertSeeText('En attente')->assertDontSeeText('Adjointe');
});

it('rend le poste effectif seulement à l’acceptation de la personne désignée, qui est la seule à répondre', function () {
    $fondateur = person('Fondatrice');
    ouvreModule('public');
    $zumra = zumraZ1($fondateur);
    $adjointe = responsableNomme($zumra);
    $autre = responsableNomme($zumra, 'Autre responsable');
    app(Memberships::class)->proposeDuty($fondateur, $zumra, ligne($zumra, $adjointe), ZumraDuty::Finance);

    // Ni le fondateur, ni un autre responsable ne répondent à sa place.
    foreach ([$fondateur, $autre] as $intrus) {
        expect(fn () => app(Memberships::class)->acceptDuty($intrus, ligne($zumra, $adjointe)))->toThrow(NotFoundHttpException::class);
    }
    expect(ligne($zumra, $adjointe)->dutyPending())->toBeTrue();

    Livewire::actingAs($adjointe)->test(MesZumras::class)->assertSee('Responsabilités proposées')->assertSee('Responsable financier')
        ->call('acceptDuty', ligne($zumra, $adjointe)->id)->assertSee('Responsabilité acceptée.');

    expect(ligne($zumra, $adjointe)->dutyEffective())->toBeTrue()->and(collect($zumra->governance())->firstWhere('label', 'Responsable financier')['state'])->toBe('occupied');
    expect(UserNotification::query()->where('user_id', $fondateur->id)->where('kind', 'zumra_duty_accepted')->exists())->toBeTrue();
    // Une fois répondu, la proposition n'existe plus.
    expect(fn () => app(Memberships::class)->declineDuty($adjointe, ligne($zumra, $adjointe)))->toThrow(ValidationException::class);
});

it('libère le poste quand la personne désignée le refuse, et le fondateur peut retirer proposition ou poste', function () {
    $fondateur = person('Fondatrice');
    ouvreModule('public');
    $zumra = zumraZ1($fondateur);
    $adjointe = responsableNomme($zumra);
    $autre = responsableNomme($zumra, 'Autre responsable');
    $service = app(Memberships::class);

    $service->proposeDuty($fondateur, $zumra, ligne($zumra, $adjointe), ZumraDuty::Finance);
    Livewire::actingAs($adjointe)->test(MesZumras::class)->call('declineDuty', ligne($zumra, $adjointe)->id)->assertSee('Responsabilité refusée.');
    expect(ligne($zumra, $adjointe)->duty)->toBeNull()->and(collect($zumra->governance())->firstWhere('label', 'Responsable financier')['state'])->toBe('free');
    expect(UserNotification::query()->where('user_id', $fondateur->id)->where('kind', 'zumra_duty_declined')->exists())->toBeTrue();

    // Une proposition en attente est remplacée ; un poste accepté doit d'abord être retiré.
    $service->proposeDuty($fondateur, $zumra, ligne($zumra, $adjointe), ZumraDuty::Finance);
    $service->proposeDuty($fondateur, $zumra, ligne($zumra, $autre), ZumraDuty::Finance);
    expect(ligne($zumra, $adjointe)->duty)->toBeNull()->and(ligne($zumra, $autre)->dutyPending())->toBeTrue();
    $service->acceptDuty($autre, ligne($zumra, $autre));
    expect(fn () => $service->proposeDuty($fondateur, $zumra, ligne($zumra, $adjointe), ZumraDuty::Finance))->toThrow(ValidationException::class);
    $service->clearDuty($fondateur, $zumra, ligne($zumra, $autre));
    expect(ligne($zumra, $autre)->duty)->toBeNull();
});

it('réserve les postes au fondateur, à un responsable nommé, et ne change aucun pouvoir', function () {
    $fondateur = person('Fondatrice');
    ouvreModule('public');
    $zumra = zumraZ1($fondateur);
    $adjointe = responsableNomme($zumra);
    $membre = person('Membre');
    ajouteMembre($zumra, $membre);
    $service = app(Memberships::class);

    expect(fn () => $service->proposeDuty($adjointe, $zumra, ligne($zumra, $adjointe), ZumraDuty::Finance))->toThrow(AuthorizationException::class);
    expect(fn () => $service->proposeDuty($fondateur, $zumra, ligne($zumra, $membre), ZumraDuty::Finance))->toThrow(ValidationException::class);

    $service->proposeDuty($fondateur, $zumra, ligne($zumra, $adjointe), ZumraDuty::Finance);
    $service->acceptDuty($adjointe, ligne($zumra, $adjointe));
    expect(ligne($zumra, $adjointe)->role)->toBe(ZumraRole::Manager)->and($zumra->roleOf($fondateur))->toBe(ZumraRole::Founder);
    // Retirer le rôle de responsable libère aussi le poste.
    $service->demote($fondateur, $zumra, ligne($zumra, $adjointe));
    expect(ligne($zumra, $adjointe)->duty)->toBeNull()->and(ligne($zumra, $adjointe)->duty_state)->toBeNull();
});

it('garde effectifs les postes déjà tenus avant l’acceptation et migre sans rien perdre', function () {
    $fondateur = person('Fondatrice');
    ouvreModule('public');
    $zumra = zumraZ1($fondateur);
    $ancien = responsableNomme($zumra, 'Ancien titulaire');
    // Donnée d'avant l'acceptation : un poste sans état.
    DB::table('zumra_memberships')->where('id', ligne($zumra, $ancien)->id)->update(['duty' => 'finance', 'duty_state' => null]);

    expect(ligne($zumra, $ancien)->dutyEffective())->toBeTrue()->and(ligne($zumra, $ancien)->dutyPending())->toBeFalse();
    $poste = collect($zumra->fresh()->governance())->firstWhere('label', 'Responsable financier');
    expect($poste['state'])->toBe('occupied')->and($poste['holder']->is($ancien))->toBeTrue();
    $this->actingAs($fondateur)->get(route('zumras.show', $zumra))->assertSeeText('Ancien titulaire');
    // Le responsable principal reste occupé sans acceptation : c'est le fondateur.
    expect(collect($zumra->governance())->first()['state'])->toBe('occupied');
});

it('montre une responsabilité proposée dans « À faire maintenant », et plus rien si le module est fermé', function () {
    $fondateur = person('Fondatrice');
    ouvreModule('public');
    $zumra = zumraZ1($fondateur);
    $adjointe = responsableNomme($zumra);
    app(Memberships::class)->proposeDuty($fondateur, $zumra, ligne($zumra, $adjointe), ZumraDuty::SecondDeputy);

    $lignes = app(Todo::class)->for($adjointe);
    expect(collect($lignes)->pluck('title')->implode(' '))->toContain('Responsabilité proposée : Deuxième responsable adjoint');
    ouvreModule('off');
    expect(collect(app(Todo::class)->for($adjointe))->pluck('title')->implode(' '))->not->toContain('Responsabilité proposée');
    expect(fn () => app(Memberships::class)->acceptDuty($adjointe, ligne($zumra, $adjointe)))->toThrow(NotFoundHttpException::class);
});
