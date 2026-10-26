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
use App\Models\Zumra;
use App\Services\Invitations;
use App\Services\JoinRequests;
use App\Services\Memberships;
use App\Services\Settings;
use App\Services\Zumras;
use App\Support\ZumraModule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

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
    $this->actingAs($simple)->get(route('zumras.create'))->assertForbidden();
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
    $page = $this->get(route('zumras.show', $zumra))->assertOk()->assertSeeText('Demander à rejoindre')->assertSeeText('Responsabilités');
    expect($page->getContent())->not->toContain('wire:click="setTab(\'projets\')"');
    $this->get(route('zumras.show', ['zumra' => $zumra, 'onglet' => 'projets']))->assertOk()->assertDontSeeText('Aucun projet');
    $this->actingAs(User::factory()->create())->get(route('projets.create', $zumra))->assertForbidden();
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

it('laisse un membre retrouver sa ZUMRA existante, module fermé (D-183)', function () {
    $admin = User::factory()->superAdmin()->create();
    ouvreModule('test');
    $zumra = zumraZ1($admin);
    $membre = person('Membre');
    ajouteMembre($zumra, $membre);
    ouvreModule('off');

    $this->actingAs($membre)->get(route('zumras.show', $zumra))->assertOk()->assertSeeText('Espace existant conservé');
    $this->actingAs($membre)->get(route('zumras.index'))->assertRedirect(route('personnes.index'));
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

it('présente les cinq responsabilités, occupées ou à pourvoir, sans score', function () {
    $admin = User::factory()->superAdmin()->create();
    ouvreModule('public');
    $zumra = zumraZ1($admin);
    $adjointe = person('Adjointe');
    $membre = person('Membre');
    ajouteMembre($zumra, $adjointe, ZumraRole::Manager);
    ajouteMembre($zumra, $membre);

    $gouvernance = collect($zumra->governance());
    expect($gouvernance)->toHaveCount(5)->and($gouvernance->where('holder', null)->count())->toBe(4);

    app(Memberships::class)->assignDuty($admin, $zumra, ligne($zumra, $adjointe), ZumraDuty::Finance);
    expect(collect($zumra->governance())->firstWhere('label', 'Responsable financier')['holder']->is($adjointe))->toBeTrue();

    // Un membre simple n'a pas de poste ; seul le fondateur en attribue.
    expect(fn () => app(Memberships::class)->assignDuty($admin, $zumra, ligne($zumra, $membre), ZumraDuty::Finance))->toThrow(ValidationException::class);
    expect(fn () => app(Memberships::class)->assignDuty($adjointe, $zumra, ligne($zumra, $adjointe), ZumraDuty::SecondDeputy))->toThrow(AuthorizationException::class);

    // Le retirer comme responsable libère le poste.
    app(Memberships::class)->demote($admin, $zumra, ligne($zumra, $adjointe));
    expect(collect($zumra->governance())->firstWhere('label', 'Responsable financier')['holder'])->toBeNull();

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
