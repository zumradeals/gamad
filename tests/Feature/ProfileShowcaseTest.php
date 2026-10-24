<?php

use App\Enums\PublicationType;
use App\Livewire\ModifierProfil;
use App\Livewire\PublierPublication;
use App\Models\Conversation;
use App\Models\PersonConnection;
use App\Models\Publication;
use App\Services\Gestures;
use App\Services\People;
use App\Services\ProfileDetails;
use App\Services\Publications;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    config(['gamad.initial_experience' => true]);
    Storage::fake('local');
    $this->a = person('Awa', ['Couture']);
    $this->b = person('Binta', ['Menuiserie']);
});

it('enregistre la présentation les expériences les savoir-faire et les publications à la une', function () {
    $post = publication($this->a, PublicationType::PersonalWork, ['body' => 'Mon premier meuble']);
    Livewire::actingAs($this->a)->test(ModifierProfil::class)
        ->set('introduction', 'Je répare et je transmets.')
        ->set('experiences', [['title' => 'Atelier de quartier', 'kind' => 'volunteer', 'period' => 'Depuis 2024', 'description' => 'Accompagnement de trois voisins.']])
        ->set('skill_details', [['skill' => 'Couture', 'description' => 'Je répare les vêtements.', 'publication_id' => $post->id]])
        ->set('featured_publications', [$post->id])->call('save')->assertHasNoErrors();
    $profile = $this->a->profile->fresh();
    expect($profile->introduction)->toBe('Je répare et je transmets.')->and($profile->experiences[0]['kind'])->toBe('volunteer')->and($profile->featured_publications)->toBe([$post->id]);
    $this->actingAs($this->b)->get(route('personnes.show', $profile))->assertOk()->assertSeeText('Atelier de quartier')->assertSeeText('Je répare les vêtements.')->assertSeeText('Mon premier meuble')->assertSeeText('Expériences déclarées');
});

it('refuse les preuves étrangères les capacités non déclarées et les expériences incomplètes', function () {
    $post = publication($this->b);
    $clean = app(ProfileDetails::class);
    foreach ([
        ['featured_publications' => [$post->id]],
        ['skill_details' => [['skill' => 'Couture', 'description' => 'Un exemple', 'publication_id' => $post->id]]],
        ['skill_details' => [['skill' => 'Comptabilité', 'description' => 'Un exemple']]],
        ['experiences' => [['title' => ' ', 'kind' => 'personal']]],
        ['experiences' => [['title' => 'Titre', 'kind' => 'certified']]],
        ['introduction' => str_repeat('a', 1201)],
        ['experiences' => array_fill(0, 11, ['title' => 'Titre', 'kind' => 'personal'])],
    ] as $data) {
        expect(fn () => $clean->clean($this->a, $data, ['Couture']))->toThrow(ValidationException::class);
    }
});

it('conserve la visibilité des preuves et publications à la une même après leur sélection', function () {
    $post = publication($this->a, PublicationType::PersonalWork, ['body' => 'Texte réservé preuve', 'visibility' => Publication::MEMBERS]);
    $this->a->profile->update(['featured_publications' => [$post->id], 'skill_details' => [['skill' => 'Couture', 'description' => 'Une description publique', 'publication_id' => $post->id]]]);
    auth()->logout();
    $this->get(route('personnes.show', $this->a->profile))->assertOk()->assertDontSeeText('Texte réservé preuve')->assertDontSee(route('publications.show', $post), false);
    $this->actingAs($this->b)->get(route('personnes.show', $this->a->profile))->assertOk()->assertSeeText('Texte réservé preuve');
    $post->update(['hidden_at' => now()]);
    $this->get(route('personnes.show', $this->a->profile))->assertOk()->assertDontSeeText('Texte réservé preuve');
});

it('ne permet jamais à un autre membre de modifier le profil enrichi de la personne', function () {
    $this->a->profile->update(['introduction' => 'Présentation de Awa']);
    Livewire::actingAs($this->b)->test(ModifierProfil::class)->set('introduction', 'Présentation de Binta')->call('save')->assertHasNoErrors();
    expect($this->a->profile->fresh()->introduction)->toBe('Présentation de Awa')->and($this->b->profile->fresh()->introduction)->toBe('Présentation de Binta');
});

it('publie les réalisations personnelles et partages sans projet et leur donne le geste Échanger', function () {
    foreach ([PublicationType::PersonalWork, PublicationType::Share] as $type) {
        Livewire::actingAs($this->a)->test(PublierPublication::class, ['slug' => $type->slug()])->set('body', 'Une publication '.$type->value)->call('publish')->assertHasNoErrors();
        $post = Publication::where('type', $type)->sole();
        expect($post->project_id)->toBeNull()->and($post->zumra_id)->toBeNull()->and($post->type->gestureLabel())->toBe('Échanger')->and($post->acceptsGestures())->toBeTrue();
        $this->actingAs($this->b)->get(route('publications.show', $post))->assertOk()->assertSeeText('Échanger');
    }
});

it('demande un motif pour échanger et attend l’accord avant d’ouvrir la conversation sans créer une relation', function () {
    $post = publication($this->a, PublicationType::Share);
    expect(fn () => app(Gestures::class)->propose($this->b, $post, '  '))->toThrow(ValidationException::class);
    $gesture = app(Gestures::class)->propose($this->b, $post, 'Comment avez-vous procédé ?');
    expect(Conversation::count())->toBe(0);
    app(Gestures::class)->accept($this->a, $gesture);
    expect(Conversation::sole()->publication_id)->toBe($post->id)->and(PersonConnection::count())->toBe(0);
});

it('normalise les images en JPEG privé en conservant leurs proportions', function () {
    $post = app(Publications::class)->publish($this->a, PublicationType::PersonalWork, ['body' => 'Un travail en images', 'media' => UploadedFile::fake()->image('travail.png', 900, 600)]);
    expect($post->media_kind)->toBe('image')->and($post->media_mime)->toBe('image/jpeg');
    Storage::disk('local')->assertExists($post->media_path);
    $info = getimagesizefromstring(Storage::disk('local')->get($post->media_path));
    expect($info[0])->toBe(900)->and($info[1])->toBe(600)->and($info['mime'])->toBe('image/jpeg');
    auth()->logout();
    $this->get(route('publications.media', $post))->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('refuse les fichiers actifs déguisés et les tailles excessives sans publication ni fichier orphelin', function () {
    foreach ([
        UploadedFile::fake()->createWithContent('image.jpg', '<script>alert(1)</script>'),
        UploadedFile::fake()->create('gros.mp4', 10241, 'video/mp4'),
        UploadedFile::fake()->create('gros.pdf', 5121, 'application/pdf'),
    ] as $file) {
        expect(fn () => app(Publications::class)->publish($this->a, PublicationType::Share, ['body' => 'Un fichier', 'media' => $file]))->toThrow(ValidationException::class);
    }
    expect(Publication::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('sert les PDF en téléchargement et vérifie la visibilité à chaque demande', function () {
    $file = UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    $post = app(Publications::class)->publish($this->a, PublicationType::Share, ['body' => 'Un document', 'visibility' => Publication::MEMBERS, 'media' => $file]);
    auth()->logout();
    $this->get(route('publications.media', $post))->assertNotFound();
    $this->actingAs($this->b)->get(route('publications.media', $post))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=Document.pdf');
    $post->update(['hidden_at' => now()]);
    $this->get(route('publications.media', $post))->assertNotFound();
});

it('révoque aussi l’accès aux fichiers en cas de blocage', function () {
    $post = app(Publications::class)->publish($this->a, PublicationType::Share, ['body' => 'Un dessin', 'media' => UploadedFile::fake()->image('dessin.png')]);
    app(People::class)->block($this->a, $this->b, true);
    $this->actingAs($this->b)->get(route('publications.media', $post))->assertNotFound();
});

it('supprime les fichiers lorsque leur publication ou leur compte est supprimé', function () {
    $post = app(Publications::class)->publish($this->a, PublicationType::Share, ['body' => 'Un dessin', 'media' => UploadedFile::fake()->image('dessin.png')]);
    $path = $post->media_path;
    $post->delete();
    Storage::disk('local')->assertMissing($path);
    $post = app(Publications::class)->publish($this->a, PublicationType::Share, ['body' => 'Un autre dessin', 'media' => UploadedFile::fake()->image('dessin.png')]);
    $path = $post->media_path;
    $this->a->delete();
    Storage::disk('local')->assertMissing($path);
});
