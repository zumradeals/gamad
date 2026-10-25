<?php

use App\Enums\PublicationType;
use App\Livewire\Fil;
use App\Livewire\FilComposer;
use App\Models\Company;
use App\Models\Publication;
use App\Models\User;
use App\Services\People;
use App\Services\Publications;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

function leFil(User $viewer)
{
    return Livewire::actingAs($viewer)->test(Fil::class);
}

function corps($composant): array
{
    return $composant->viewData('posts')->pluck('body')->all();
}

beforeEach(function () {
    $this->moi = person('Moi Même', ['Menuiserie'], ['Comptabilité'], 'Bouaké');
    $this->awa = person('Awa Traoré', ['Comptabilité'], ['Anglais'], 'Bouaké');
    $this->yao = person('Yao Kouassi', ['Jardinage'], ['Menuiserie'], 'Korhogo');
});

it('affiche les six onglets du Fil et rattache chacun à des contenus réels', function () {
    $this->actingAs($this->moi)->get(route('fil'))->assertOk()
        ->assertSeeText('Savoirs')->assertSeeText('Réalisations')->assertSeeText('Besoins')->assertSeeText('Personnes')->assertSeeText('Entreprises')
        ->assertSeeText('Tri : Plus récents')->assertSeeText('sans classement de popularité');

    publication($this->awa, PublicationType::Knowledge, ['body' => 'Un savoir partagé']);
    publication($this->awa, PublicationType::PersonalWork, ['body' => 'Une réalisation montrée']);
    publication($this->yao, PublicationType::Need, ['body' => 'Un besoin exprimé']);

    expect(corps(leFil($this->moi)->call('setFilter', 'savoirs')))->toBe(['Un savoir partagé'])
        ->and(corps(leFil($this->moi)->call('setFilter', 'realisations')))->toBe(['Une réalisation montrée'])
        ->and(corps(leFil($this->moi)->call('setFilter', 'besoins')))->toBe(['Un besoin exprimé'])
        ->and(corps(leFil($this->moi)->call('setFilter', 'personnes')))->toHaveCount(3);
});

it('sépare les publications de personnes de celles des entreprises', function () {
    $proprio = User::factory()->create();
    $entreprise = new Company(['name' => 'Alpha Compta', 'legal_name' => 'Alpha SARL', 'registration_country' => 'CI', 'registration_number' => 'X1', 'sector' => 'Comptabilité']);
    $entreprise->slug = 'alpha-compta-'.strtolower(Str::random(8));
    $entreprise->forceFill(['owner_id' => $proprio->id, 'status' => Company::VERIFIED, 'verified_at' => now()])->save();
    $post = app(Publications::class)->publish($proprio, PublicationType::Share, ['body' => 'Nouvelle de l’entreprise', 'visibility' => 'public'], company: $entreprise);
    publication($this->awa, PublicationType::Share, ['body' => 'Un partage de personne']);

    expect(corps(leFil($this->moi)->call('setFilter', 'personnes')))->toBe(['Un partage de personne'])
        ->and(corps(leFil($this->moi)->call('setFilter', 'entreprises')))->toBe(['Nouvelle de l’entreprise']);
    expect($post->company_id)->toBe($entreprise->id);
});

it('ne présente aucun « j’aime », aucun classement et un seul tri, par date', function () {
    publication($this->awa, PublicationType::Share, ['body' => 'Premier']);
    publication($this->yao, PublicationType::Share, ['body' => 'Second']);

    $html = leFil($this->moi)->html();
    expect($html)->not->toContain('J’aime')->not->toContain('Pertinence')->not->toContain('Populaire');
    expect(corps(leFil($this->moi)))->toBe(['Second', 'Premier']);
});

it('affiche la carte de profil réelle et le lien vers le profil public', function () {
    leFil($this->moi)->assertSeeText('Moi Même')->assertSeeText('Bouaké')->assertSeeText('Menuiserie')->assertSeeText('Comptabilité')
        ->assertSeeText('Voir mon profil public')->assertSee(route('personnes.show', $this->moi->profile->public_id), false);
});

it('relie la navigation de gauche à des routes existantes', function () {
    $html = leFil($this->moi)->html();

    foreach ([route('espace', ['rubrique' => 'publications']), route('espace.enregistrements'), route('espace', ['rubrique' => 'echanges']), route('espace', ['rubrique' => 'parametres'])] as $url) {
        expect($html)->toContain('href="'.$url.'"');
    }
    leFil($this->moi)->assertSeeText('Fil d’actualité')->assertSeeText('À faire maintenant');
});

it('affiche « Mon activité » avec des compteurs réels, seulement quand il y a une activité', function () {
    leFil($this->moi)->assertDontSeeText('Mon activité sur GAMAD');

    $post = publication($this->moi, PublicationType::Share, ['body' => 'Ma publication']);
    publication($this->moi, PublicationType::Share, ['body' => 'Autre']);
    DB::table('publication_bookmarks')->insert(['user_id' => $this->moi->id, 'publication_id' => publication($this->awa)->id]);

    $page = leFil($this->moi);
    expect($page->viewData('activity'))->toBe(['Publications' => 2, 'Enregistrements' => 1, 'Échanges' => 0]);
    $page->assertSeeText('Mon activité sur GAMAD');
    expect($post->exists)->toBeTrue();
});

it('relie Explorer GAMAD à des destinations fonctionnelles, sans ZUMRA ni Projets', function () {
    $html = leFil($this->moi)->html();

    expect($html)->toContain('href="'.route('personnes.index').'"')->toContain('href="'.route('entreprises.index').'"')
        ->toContain('href="'.route('fil', ['filtre' => 'besoins']).'"')->toContain('href="'.route('fil', ['filtre' => 'savoirs']).'"')
        ->not->toContain(route('zumras.index'))->not->toContain(route('projets.index'));
    leFil($this->moi)->assertSeeText('Trouver une personne')->assertSeeText('Besoins à découvrir')->assertSeeText('Savoirs à explorer');
});

it('suggère des personnes avec une raison explicable, sans moi ni mes relations, et permet de les suivre', function () {
    $suggestions = leFil($this->moi)->viewData('suggestions');

    expect($suggestions->pluck('user.name')->all())->toContain('Awa Traoré')->toContain('Yao Kouassi')->not->toContain('Moi Même')
        ->and($suggestions->firstWhere('user.name', 'Awa Traoré')->reason)->toBe('Peut vous apporter : Comptabilité')
        ->and($suggestions->firstWhere('user.name', 'Yao Kouassi')->reason)->toBe('Veut apprendre : Menuiserie');

    $page = leFil($this->moi)->call('followSuggestion', $this->awa->profile->id);
    expect(DB::table('person_follows')->where('user_id', $this->moi->id)->where('followed_id', $this->awa->id)->exists())->toBeTrue();
    // Une personne suivie n'est plus suggérée ; le suivi n'ouvre aucun contact.
    expect(leFil($this->moi)->viewData('suggestions')->pluck('user.name')->all())->not->toContain('Awa Traoré');
    expect(DB::table('person_connections')->count())->toBe(0);
    $page->assertHasNoErrors();
});

it('masque le bloc de suggestions quand il n’y a personne à suggérer', function () {
    $seul = User::factory()->create();
    leFil($seul)->assertDontSeeText('Suggestions pour vous');
});

it('ne suggère pas une personne bloquée ni un profil réservé aux inscrits à un visiteur', function () {
    app(People::class)->block($this->awa, $this->moi, true);

    expect(leFil($this->moi)->viewData('suggestions')->pluck('user.name')->all())->not->toContain('Awa Traoré');
});

it('refuse un identifiant de suggestion invalide et le Fil à un visiteur', function () {
    $this->get(route('fil'))->assertRedirect(route('home'));
    leFil($this->moi)->call('followSuggestion', 'pas-un-uuid')->assertStatus(404);
    expect(DB::table('person_follows')->count())->toBe(0);
});

it('propose le bloc « Chaque contribution compte » qui ouvre le vrai composer', function () {
    $html = leFil($this->moi)->html();

    expect($html)->toContain('Chaque contribution compte')->toContain('Partager maintenant')->toContain("\$dispatch('open-composer')");
    expect(file_get_contents(resource_path('views/livewire/fil-composer.blade.php')))->toContain('x-on:open-composer.window');
});

it('garde le composer fermé compact, sans bouton Publier visible, et propose les raccourcis réels', function () {
    $html = Livewire::actingAs($this->moi)->test(FilComposer::class)->html();

    expect($html)->toContain('Que souhaitez-vous partager ?')->toContain('Photo')->toContain('Réalisation')
        ->toContain(route('agir.publier', 'savoir'))->toContain(route('agir.publier', 'besoin'));
    // Le bouton Publier n'existe que dans l'éditeur déplié.
    expect(strpos($html, 'x-show="open"'))->toBeLessThan(strpos($html, '>Publier</button>'));
});

it('publie réellement depuis le composer, avec validation, sans perdre la saisie sur erreur', function () {
    Storage::fake('local');
    $page = Livewire::actingAs($this->moi)->test(FilComposer::class);

    $page->set('body', '')->call('publish')->assertHasErrors(['body' => 'required']);
    $page->set('body', str_repeat('a', Publication::BODY_MAX + 1))->call('publish')->assertHasErrors(['body']);
    expect($page->get('body'))->toHaveLength(Publication::BODY_MAX + 1);

    $page->set('body', 'Bonjour GAMAD')->set('kind', 'personal_work')->call('publish')->assertHasNoErrors()->assertSet('body', '')->assertSet('expanded', false);
    expect(Publication::query()->where('author_id', $this->moi->id)->where('body', 'Bonjour GAMAD')->where('type', 'personal_work')->exists())->toBeTrue();
});

it('refuse un fichier non autorisé dans le composer et conserve la validation de visibilité', function () {
    $page = Livewire::actingAs($this->moi)->test(FilComposer::class);

    $page->set('body', 'Avec un exécutable')->set('media', UploadedFile::fake()->create('virus.exe', 10))->call('publish')->assertHasErrors(['media']);
    $page->set('media', null)->set('visibility', 'secret')->call('publish')->assertHasErrors(['visibility']);
});

it('annule le brouillon : vide l’éditeur et le ferme', function () {
    $page = Livewire::actingAs($this->moi)->test(FilComposer::class)->set('body', 'Brouillon')->set('expanded', true);

    $page->call('cancel')->assertSet('body', '')->assertSet('expanded', false);
    expect(Publication::query()->where('author_id', $this->moi->id)->count())->toBe(0);
});

it('protège le brouillon : l’éditeur ne se ferme pas tout seul tant qu’il contient du texte ou un fichier', function () {
    $source = file_get_contents(resource_path('views/livewire/fil-composer.blade.php'));

    expect($source)->toContain('close() { if (! this.dirty())')
        ->toContain("confirm('Abandonner cette publication")
        ->toContain('x-on:click.outside="close()"')
        ->toContain('x-on:keydown.escape.window="close()"');
});

it('ne montre pas d’actions sans effet : « Signaler » seulement sur la publication d’un autre, via le menu', function () {
    publication($this->awa, PublicationType::Share, ['body' => 'La publication d’Awa']);
    publication($this->moi, PublicationType::Share, ['body' => 'Ma publication']);

    $html = leFil($this->moi)->html();
    expect(substr_count($html, 'publications.signaler') + substr_count($html, '/signaler'))->toBe(1)
        ->and($html)->toContain('Autres actions pour cette publication');
});

it('n’expose ni e-mail, ni téléphone dans le Fil', function () {
    publication($this->awa, PublicationType::Share, ['body' => 'Un partage']);

    expect(leFil($this->moi)->html())->not->toContain($this->awa->email)->not->toContain($this->moi->email);
});
