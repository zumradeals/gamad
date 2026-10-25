<?php

use App\Livewire\PublierPublication;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const AGIR_ACTIONS = [
    'besoin' => ['J’ai besoin d’aide', 'De quoi avez-vous besoin ?', 'Publier ma demande'],
    'apprendre' => ['Je veux apprendre', 'Qu’aimeriez-vous apprendre ?', 'Publier ma demande d’apprentissage'],
    'aide' => ['Je peux aider', 'Que pouvez-vous faire pour aider quelqu’un ?', 'Publier ma proposition'],
    'savoir' => ['Je partage un savoir', 'Qu’aimeriez-vous expliquer ou apprendre aux autres ?', 'Partager mon savoir'],
    'ma-realisation' => ['Je montre une réalisation', 'Qu’avez-vous réalisé ?', 'Partager ma réalisation'],
];

it('ouvre les cinq actions depuis le menu de bureau et la feuille mobile, sans passer par /agir', function () {
    $html = $this->actingAs(User::factory()->create())->get('/fil')->assertOk()->getContent();

    expect($html)->toContain('id="agir-menu-desktop"')->toContain('id="agir-menu-mobile"')->toContain('Que voulez-vous faire ?');
    foreach (AGIR_ACTIONS as $slug => [$titre]) {
        expect(substr_count($html, 'href="'.route('agir.publier', $slug).'" data-agir-action'))->toBeGreaterThanOrEqual(2);
        $this->assertStringContainsString($titre, html_entity_decode($html, ENT_QUOTES));
    }
    // Le menu mobile se ferme par le bouton, l’arrière-plan et Échap.
    expect($html)->toContain('aria-label="Fermer"')->toContain('x-on:keydown.escape.window="agirOpen = false"');
});

it('ouvre directement chaque formulaire avec sa question principale', function () {
    foreach (AGIR_ACTIONS as $slug => [$titre, $question]) {
        $this->actingAs(User::factory()->create())->get(route('agir.publier', $slug))->assertOk()
            ->assertSeeText($titre)->assertSeeText($question)->assertSeeText('Détails')->assertSeeText('Compléments')->assertSeeText('Aperçu')
            ->assertSeeText('Continuer');
    }
});

it('suit trois étapes, revient en arrière sans rien perdre et publie réellement', function () {
    $user = User::factory()->create(['name' => 'Awa Koné']);
    $test = Livewire::actingAs($user)->test(PublierPublication::class, ['slug' => 'besoin'])
        ->assertSeeText('De quoi avez-vous besoin ?')->assertSet('step', 1)
        ->call('next')->assertHasErrors('body')->assertSet('step', 1)
        ->set('body', 'Je cherche quelqu’un pour installer une caméra dans ma boutique à Abidjan.')
        ->call('next')->assertHasNoErrors()->assertSet('step', 2)
        ->assertSeeText('Où ?')->assertSeeText('Pour quand ?')->assertSeeText('Tout le monde')->assertSeeText('Seulement les membres GAMAD')
        ->set('needTiming', 'Dès que possible')->set('visibility', Publication::MEMBERS)
        ->call('back')->assertSet('step', 1)->assertSet('body', 'Je cherche quelqu’un pour installer une caméra dans ma boutique à Abidjan.')
        ->call('next')->assertSet('needTiming', 'Dès que possible')->assertSet('visibility', Publication::MEMBERS)
        ->call('preview')->assertSet('previewing', true)
        ->assertSeeText('Relisez avant de publier')->assertSeeText('Awa Koné')->assertSeeText('installer une caméra')
        ->assertSeeText('Dès que possible')->assertSeeText('Seulement les membres GAMAD')->assertSeeText('Publier ma demande')
        ->call('back')->assertSet('previewing', false)->assertSet('step', 2)
        ->call('preview')->call('edit')->assertSet('previewing', false)->assertSet('step', 1);
    expect(Publication::where('author_id', $user->id)->count())->toBe(0);

    $test->call('next')->call('preview')->call('publish')->assertHasNoErrors()->assertRedirect();
    $publication = Publication::where('author_id', $user->id)->sole();
    expect($publication->visibility)->toBe(Publication::MEMBERS)->and($publication->body)->toContain('Où et quand : Dès que possible');
});

it('garde un seul champ obligatoire et refuse un texte trop long avec un message simple', function () {
    Livewire::actingAs(User::factory()->create())->test(PublierPublication::class, ['slug' => 'ma-realisation'])
        ->set('body', str_repeat('a', 501))->call('next')->assertHasErrors('body')->assertSet('step', 1)
        ->set('body', 'J’ai réparé une machine.')->call('next')->assertHasNoErrors()->assertSet('step', 2)
        ->call('preview')->call('publish')->assertHasNoErrors()->assertRedirect();
});

it('refuse un mauvais format de fichier sans le garder, accepte une image et la retire', function () {
    Storage::fake('local');
    $test = Livewire::actingAs(User::factory()->create())->test(PublierPublication::class, ['slug' => 'savoir'])
        ->set('body', 'Un savoir.')->call('next')
        ->set('media', UploadedFile::fake()->create('virus.exe', 20, 'application/x-msdownload'))
        ->assertHasErrors('media')->assertSet('media', null)->assertSeeText('Ce fichier ne peut pas être ajouté')
        ->set('media', UploadedFile::fake()->image('photo.jpg', 400, 300))->assertHasNoErrors('media')
        ->assertSeeText('photo.jpg')->call('removeMedia')->assertSet('media', null)
        ->set('media', UploadedFile::fake()->image('photo.jpg', 400, 300))->call('preview')->assertSeeText('Relisez avant de publier')
        ->call('publish')->assertHasNoErrors()->assertRedirect();
    $publication = Publication::query()->latest('created_at')->firstOrFail();
    expect($publication->media_path)->not->toBeNull();
});

it('n’affiche aucun avis technique de brouillon tant qu’aucun texte n’est récupérable, et garde les formulaires de ZUMRA intacts', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('agir.publier', 'besoin'))->assertOk()->getContent();
    expect($html)->not->toContain('Brouillon local sur cet appareil')->not->toContain('Effacer la copie sauvegardée')
        ->toContain('x-show="saved"');
});

it('interdit toujours le formulaire à un visiteur et garde /agir pour les anciens liens', function () {
    $this->get('/agir/besoin')->assertRedirect('/login');
    $this->get('/agir')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/agir')->assertOk()->assertSeeText('Je montre une réalisation');
});
