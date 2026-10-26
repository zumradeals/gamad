<?php

use App\Livewire\CreerCampagne;
use App\Livewire\MesCampagnes;
use App\Models\AdCampaign;
use App\Models\Skill;
use App\Models\User;
use App\Services\Publicite\Campaigns;
use App\Services\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** Écrans « Créer une campagne » et « Mes campagnes » (D-209 à D-212). */
function ecranOuvrir(string $etat = 'public'): void
{
    app(Settings::class)->update(['publicite_module' => $etat, 'publicite_min_audience' => 20], User::factory()->superAdmin()->create());
}

function ecranCampagne(User $u, array $remplace = []): AdCampaign
{
    return app(Campaigns::class)->save($u, null, array_replace([
        'objective' => 'contact', 'cta' => 'exchange', 'title' => 'Cours de couture', 'body' => 'Apprenez à coudre en petit groupe.', 'audience_mode' => 'contextual',
        'budget_fcfa' => 30000, 'starts_on' => now('Africa/Abidjan')->addDays(2)->toDateString(), 'ends_on' => now('Africa/Abidjan')->addDays(16)->toDateString(),
    ], $remplace));
}

function ecranRemplir($c, array $v = [])
{
    $v += ['objective' => 'contact', 'title' => 'Cours de couture', 'body' => 'Apprenez à coudre en petit groupe.', 'audience_mode' => 'contextual', 'budget' => '30 000',
        'starts_on' => now('Africa/Abidjan')->addDays(2)->toDateString(), 'ends_on' => now('Africa/Abidjan')->addDays(16)->toDateString()];
    foreach ($v as $k => $val) {
        $c = $c->set($k, $val);
    }

    return $c;
}

beforeEach(fn () => ecranOuvrir());

it('ferme les écrans quand le service est désactivé, et les refuse aux visiteurs', function () {
    $this->get('/espace/publicite')->assertRedirect('/login');
    $this->get('/espace/publicite/nouvelle')->assertRedirect('/login');
    ecranOuvrir('off');
    $moi = User::factory()->create();
    $this->actingAs($moi)->get('/espace/publicite')->assertNotFound();
    $this->actingAs($moi)->get('/espace/publicite/nouvelle')->assertNotFound();
    $this->actingAs(User::factory()->superAdmin()->create())->get('/espace/publicite')->assertNotFound();
});

it('montre l’état vide de Mes campagnes, avec le budget prévisionnel et les trois onglets', function () {
    $html = $this->actingAs(User::factory()->create())->get('/espace/publicite')->assertOk()->assertSee('Aucune campagne pour l’instant')->assertSee('Budget prévisionnel')->getContent();
    expect($html)->toContain('Créer une campagne')->toContain('Mes campagnes');
    expect($html)->toContain('>Résultats<')->and($html)->toContain(route('publicite.resultats'));
});

it('affiche les six étapes, l’aperçu étiqueté Sponsorisé et la mention prévisionnelle', function () {
    $html = $this->actingAs(User::factory()->create())->get('/espace/publicite/nouvelle')->assertOk()->getContent();
    foreach (['Annonceur', 'Objectif', 'Contenu', 'Audience', 'Budget et durée', 'Vérifier et soumettre', 'Sponsorisé', 'Pourquoi cette annonce ?', 'aucun paiement n’est demandé', 'Portée estimée'] as $texte) {
        expect($html)->toContain($texte);
    }
    expect($html)->toContain('Faire connaître')->toContain('Recevoir des contacts')->toContain('Faire visiter');
});

it('enregistre un brouillon depuis l’écran', function () {
    $moi = User::factory()->create();
    ecranRemplir(Livewire::actingAs($moi)->test(CreerCampagne::class))->call('saveDraft')->assertRedirect(route('publicite.campagnes'));

    $c = AdCampaign::query()->sole();
    expect($c->state)->toBe('draft')->and($c->budget_fcfa)->toBe(30000)->and($c->title)->toBe('Cours de couture')->and($c->user_id)->toBe($moi->id);
    $this->actingAs($moi)->get('/espace/publicite')->assertSee('Brouillon enregistré')->assertSee('Cours de couture')->assertSee('Brouillon');
});

it('propose le bouton adapté à l’objectif choisi', function () {
    $c = Livewire::actingAs(User::factory()->create())->test(CreerCampagne::class)->set('objective', 'contact');
    expect($c->get('cta'))->toBe('exchange');
    $c->set('objective', 'visit');
    expect($c->get('cta'))->toBe('profile');
});

it('exige la confirmation des règles avant l’envoi', function () {
    $c = ecranRemplir(Livewire::actingAs(User::factory()->create())->test(CreerCampagne::class), ['cta' => 'exchange'])->call('submit');
    $c->assertHasErrors('rulesAccepted');
    expect(AdCampaign::query()->count())->toBe(0);
});

it('envoie une campagne à la modération depuis l’écran', function () {
    $moi = User::factory()->create();
    ecranRemplir(Livewire::actingAs($moi)->test(CreerCampagne::class))->set('rulesAccepted', true)->call('submit')->assertHasNoErrors()->assertRedirect(route('publicite.campagnes'));

    expect(AdCampaign::query()->sole()->state)->toBe('review');
    $this->actingAs($moi)->get('/espace/publicite')->assertSee('envoyée à la modération')->assertSee('En examen');
});

it('signale les erreurs par champ et garde le brouillon quand l’envoi échoue', function () {
    $moi = User::factory()->create();
    ecranRemplir(Livewire::actingAs($moi)->test(CreerCampagne::class), ['budget' => '200'])->set('rulesAccepted', true)->call('submit')->assertHasErrors('budget_fcfa');
    expect(AdCampaign::query()->sole()->state)->toBe('draft');
});

it('refuse une audience trop restreinte avec son niveau, sans jamais montrer un nombre', function () {
    $moi = User::factory()->create();
    $c = ecranRemplir(Livewire::actingAs($moi)->test(CreerCampagne::class), ['audience_mode' => 'personalized'])->assertSee('Trop restreinte')->assertDontSee('1 200');
    $c->set('rulesAccepted', true)->call('submit')->assertHasErrors('audience');
    expect(AdCampaign::query()->sole()->state)->toBe('draft');
});

it('refuse une capacité qui n’existe pas dans le référentiel, sans la créer', function () {
    $c = Livewire::actingAs(User::factory()->create())->test(CreerCampagne::class)->set('skills', ['Capacité inventée'])->call('saveDraft');
    $c->assertHasErrors('skillsInput');
    expect(Skill::query()->where('name', 'Capacité inventée')->exists())->toBeFalse();
});

it('enregistre l’image de l’annonce en privé, ré-encodée, et ne la sert qu’à l’annonceur et à la modération', function () {
    Storage::fake('local');
    $moi = User::factory()->create();
    $png = UploadedFile::fake()->image('atelier.png', 600, 400);
    ecranRemplir(Livewire::actingAs($moi)->test(CreerCampagne::class))->set('image', $png)->call('saveDraft')->assertHasNoErrors();
    $c = AdCampaign::query()->sole();
    expect($c->image_path)->toStartWith('ad-images/')->and($c->image_path)->toEndWith('.jpg');
    Storage::disk('local')->assertExists($c->image_path);

    $this->actingAs($moi)->get(route('publicite.image', $c))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs(User::factory()->create())->get(route('publicite.image', $c))->assertNotFound();
    $this->actingAs(User::factory()->moderator()->create())->get(route('publicite.image', $c))->assertNotFound();
    app(Campaigns::class)->submit($moi, $c->fresh()->forceFill(['audience_mode' => 'contextual']));
    $this->actingAs(User::factory()->moderator()->create())->get(route('publicite.image', $c))->assertOk();
});

it('redirige un visiteur sans compte qui demande l’image', function () {
    $c = ecranCampagne(User::factory()->create());
    $this->get(route('publicite.image', $c))->assertRedirect('/login');
});

it('refuse un fichier qui n’est pas une image', function () {
    Storage::fake('local');
    ecranRemplir(Livewire::actingAs(User::factory()->create())->test(CreerCampagne::class))->set('image', UploadedFile::fake()->create('script.pdf', 10, 'application/pdf'))->call('saveDraft')->assertHasErrors('image');
    expect(AdCampaign::query()->count())->toBe(0);
});

it('ne permet de modifier que ses propres campagnes', function () {
    $moi = User::factory()->create();
    $autre = User::factory()->create();
    $c = ecranCampagne($autre);
    $this->actingAs($moi)->get(route('publicite.modifier', $c))->assertNotFound();
    $this->actingAs($autre)->get(route('publicite.modifier', $c))->assertOk()->assertSee('Cours de couture');
});

it('renvoie en examen la modification d’une annonce acceptée', function () {
    $moi = User::factory()->create();
    $c = app(Campaigns::class)->moderate(User::factory()->moderator()->create(), app(Campaigns::class)->submit($moi, ecranCampagne($moi)), true);
    expect($c->state)->toBe('ready');

    ecranRemplir(Livewire::actingAs($moi)->test(CreerCampagne::class, ['campaign' => $c]), ['title' => 'Titre revu'])->set('rulesAccepted', true)->call('submit')->assertRedirect(route('publicite.campagnes'));
    expect($c->fresh()->state)->toBe('review')->and($c->fresh()->title)->toBe('Titre revu');
});

it('liste les campagnes de la personne seulement, et filtre par état', function () {
    $moi = User::factory()->create();
    $autre = User::factory()->create();
    ecranCampagne($moi, ['title' => 'Mon brouillon']);
    app(Campaigns::class)->submit($moi, ecranCampagne($moi, ['title' => 'Mon envoi']));
    ecranCampagne($autre, ['title' => 'Campagne d’autrui']);

    $c = Livewire::actingAs($moi)->test(MesCampagnes::class)->assertSee('Mon brouillon')->assertSee('Mon envoi')->assertDontSee('Campagne d’autrui')->assertSee('Toutes · 2');
    $c->call('setFilter', 'review')->assertSee('Mon envoi')->assertDontSee('Mon brouillon');
});

it('applique les gestes de Mes campagnes : retirer, supprimer, dupliquer, arrêter avec confirmation', function () {
    $moi = User::factory()->create();
    $modo = User::factory()->moderator()->create();
    $service = app(Campaigns::class);
    $envoyee = $service->submit($moi, ecranCampagne($moi, ['title' => 'À retirer']));
    $page = Livewire::actingAs($moi)->test(MesCampagnes::class);

    $page->call('withdraw', $envoyee->id)->assertSee('de retour dans vos brouillons');
    expect($envoyee->fresh()->state)->toBe('draft');
    $page->call('delete', $envoyee->id)->assertSee('brouillon est supprimé');
    expect(AdCampaign::query()->count())->toBe(0);

    $prete = $service->moderate($modo, $service->submit($moi, ecranCampagne($moi, ['title' => 'Prête'])), true);
    $page->call('askStop', $prete->id)->assertSee('L’arrêt est définitif')->assertSee('Arrêter définitivement');
    $page->call('cancelStop')->assertDontSee('L’arrêt est définitif');
    expect($prete->fresh()->state)->toBe('ready');
    $page->call('askStop', $prete->id)->call('stop', $prete->id)->assertSee('La campagne est arrêtée');
    expect($prete->fresh()->state)->toBe('done');

    $page->call('duplicate', $prete->id)->assertSee('Un nouveau brouillon');
    expect(AdCampaign::query()->where('state', 'draft')->count())->toBe(1);
});

it('refuse la pause d’une campagne qui n’est pas active et le dit clairement', function () {
    $moi = User::factory()->create();
    $c = app(Campaigns::class)->moderate(User::factory()->moderator()->create(), app(Campaigns::class)->submit($moi, ecranCampagne($moi)), true);
    Livewire::actingAs($moi)->test(MesCampagnes::class)->call('pause', $c->id)->assertSee('n’est pas possible dans l’état actuel');
    expect($c->fresh()->state)->toBe('ready');
});

it('interdit d’agir sur la campagne d’autrui', function () {
    $moi = User::factory()->create();
    $c = ecranCampagne(User::factory()->create());
    Livewire::actingAs($moi)->test(MesCampagnes::class)->call('delete', $c->id)->assertNotFound();
    expect(AdCampaign::query()->whereKey($c->id)->exists())->toBeTrue();
});

it('montre le motif d’un refus et propose de modifier', function () {
    $moi = User::factory()->create();
    $c = app(Campaigns::class)->moderate(User::factory()->moderator()->create(), app(Campaigns::class)->submit($moi, ecranCampagne($moi)), false, 'Promesse de gain non permise.');
    $this->actingAs($moi)->get('/espace/publicite')->assertSee('Refusée')->assertSee('Promesse de gain non permise.')->assertSee('Modifier');
});

it('liste GAMAD Publicité dans Services GAMAD et dans Mon espace, avec son entrée', function () {
    $moi = User::factory()->create();
    $this->actingAs($moi)->get('/espace/services')->assertOk()->assertSee('GAMAD Publicité')->assertSee(route('publicite.campagnes'));
    $this->actingAs($moi)->get('/espace')->assertSee('Services GAMAD');
});
