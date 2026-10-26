<?php

use App\Enums\PublicationType;
use App\Livewire\Fil;
use App\Models\Company;
use App\Models\Publication;
use App\Models\User;
use App\Services\CompanyRegistry;
use App\Services\PublicationBookmarks;
use App\Services\PublicationComments;
use App\Services\Publications;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function companyForPublishing(User $owner, bool $verified = true): Company
{
    $company = app(CompanyRegistry::class)->submit($owner, [
        'name' => 'Atelier et Télécom '.substr($owner->id, 0, 5),
        'legal_name' => 'Atelier et Télécom SARL '.substr($owner->id, 0, 5),
        'registration_country' => 'CI',
        'registration_number' => 'CI-ABJ-PUBLICATIONS-'.str_replace('-', '', $owner->id),
        'sector' => 'Installation et maintenance',
        'city' => 'Abidjan',
    ], ['rccm' => UploadedFile::fake()->create('registre.pdf', 90, 'application/pdf')]);

    if ($verified) {
        app(CompanyRegistry::class)->review(User::factory()->superAdmin()->create(), $company, 1, 'verify');
    }

    return $company->fresh();
}

beforeEach(function () {
    config(['gamad.initial_experience' => true]);
    Storage::fake('local');
});

it('publie une actualité entreprise dans le Fil sans transformer le profil personnel du gestionnaire', function () {
    $owner = person('Gestionnaire atelier');
    $company = companyForPublishing($owner);
    $viewer = person('Lectrice des entreprises');
    $post = app(Publications::class)->publish($owner, PublicationType::Share, [
        'body' => 'Notre atelier ouvre un espace de découverte.',
        'visibility' => Publication::PUBLIC,
    ], company: $company);

    expect($post->company_id)->toBe($company->id)
        ->and($post->author_id)->toBe($owner->id)
        ->and($post->displayLabel())->toBe('Actualité d’entreprise');

    Livewire::actingAs($viewer)->test(Fil::class)
        ->assertSeeText($company->name)
        ->assertSeeText('Notre atelier ouvre un espace de découverte.')
        ->assertDontSeeText('Gestionnaire atelier')
        ->call('setFilter', 'entreprises')->assertSeeText('Notre atelier ouvre un espace de découverte.');

    $this->actingAs($viewer)->get(route('entreprises.show', $company))
        ->assertOk()->assertSeeText('Notre atelier ouvre un espace de découverte.')
        ->assertSeeText('Voir, commenter ou partager');
    $this->get(route('personnes.show', $owner->profile))
        ->assertOk()->assertDontSeeText('Notre atelier ouvre un espace de découverte.');
});

it('permet au gestionnaire d’annoncer une réalisation et renvoie les opportunités vers le module dédié', function () {
    $owner = person('Page entreprise test');
    $company = companyForPublishing($owner);
    $this->actingAs($owner)->get(route('entreprises.posts.create', $company))
        ->assertOk()->assertSeeText('Actualité')->assertSeeText('Réalisation')->assertSee(route('opportunites.create', $company), false);

    $this->post(route('entreprises.posts.store', $company), [
        'kind' => 'work', 'body' => 'Installation et maintenance livrées.',
    ])->assertRedirect(route('entreprises.show', $company).'#publications');
    $work = Publication::where('company_id', $company->id)->sole();
    expect($work->type)->toBe(PublicationType::PersonalWork)
        ->and($work->displayLabel())->toBe('Réalisation d’entreprise');

    // Une seule source pour les opportunités : le formulaire du Fil n'en crée plus.
    $this->post(route('entreprises.posts.store', $company), [
        'kind' => 'opportunity', 'body' => 'Nous recherchons un prestataire en fibre optique.',
    ])->assertSessionHasErrors('kind');
    expect(Publication::where('company_id', $company->id)->where('type', PublicationType::Need->value)->count())->toBe(0);
});

it('interdit la publication au nom d’une entreprise non vérifiée ou appartenant à un tiers', function () {
    $owner = person('Propriétaire non vérifié');
    $intruder = person('Personne sans autorisation');
    $pending = companyForPublishing($owner, false);
    $service = app(Publications::class);
    $data = ['body' => 'Publication non autorisée', 'visibility' => Publication::PUBLIC];

    $this->actingAs($owner)->get(route('entreprises.posts.create', $pending))->assertForbidden();
    $this->actingAs($owner)->post(route('entreprises.posts.store', $pending), [
        'kind' => 'news', 'body' => 'Tentative',
    ])->assertForbidden();
    expect(fn () => $service->publish($owner, PublicationType::Share, $data, company: $pending))
        ->toThrow(HttpException::class);

    $verified = companyForPublishing(person('Autre entreprise'));
    $this->actingAs($intruder)->post(route('entreprises.posts.store', $verified), [
        'kind' => 'news', 'body' => 'Usurpation de la page',
    ])->assertForbidden();
    expect(fn () => $service->publish($intruder, PublicationType::Share, $data, company: $verified))
        ->toThrow(HttpException::class);
    expect(Publication::whereNotNull('company_id')->exists())->toBeFalse();
});

it('interdit les types collectifs et les audiences non publiques pour une entreprise', function () {
    $owner = person('Gestionnaire restrictions');
    $company = companyForPublishing($owner);
    $pub = app(Publications::class);

    foreach ([PublicationType::LearningWish, PublicationType::Knowledge, PublicationType::ProjectUpdate] as $type) {
        expect(fn () => $pub->publish($owner, $type, ['body' => 'Publicité interdite'], company: $company))
            ->toThrow(ValidationException::class);
    }

    expect(fn () => $pub->publish($owner, PublicationType::Share, [
        'body' => 'Secret interdit', 'visibility' => Publication::MEMBERS,
    ], company: $company))->toThrow(ValidationException::class);

    expect(Publication::whereNotNull('company_id')->count())->toBe(0);
});

it('intègre les entreprises suivies dans Mes suivis sans suivre indirectement leur gestionnaire', function () {
    $owner = person('Gestionnaire suivi entreprise');
    $viewer = person('Lecteur abonné');
    $company = companyForPublishing($owner);
    $post = app(Publications::class)->publish($owner, PublicationType::Share, [
        'body' => 'Des nouvelles réservées au fil des abonnés.',
    ], company: $company);

    $feed = Livewire::actingAs($viewer)->test(Fil::class)
        ->call('setFilter', 'suivis')->assertDontSeeText('Des nouvelles réservées au fil des abonnés.');
    $this->actingAs($viewer)->post(route('entreprises.follow', $company))->assertRedirect();
    Livewire::actingAs($viewer)->test(Fil::class)
        ->call('setFilter', 'suivis')->assertSeeText('Des nouvelles réservées au fil des abonnés.')
        ->call('setFilter', 'decouvrir')->assertDontSeeText('Des nouvelles réservées au fil des abonnés.');

    // Même s'il s'agit du compte auteur de la publication, on ne suit pas indirectement la personne.
    $this->assertDatabaseMissing('person_follows', ['user_id' => $viewer->id, 'followed_id' => $owner->id]);
    $feed->call('setFilter', 'entreprises')->assertSeeText('Des nouvelles réservées au fil des abonnés.');
    expect(fn () => Livewire::actingAs($viewer)->test(Fil::class)->call('followAuthor', $post->id))
        ->toThrow(ModelNotFoundException::class);
});

it('rend une entreprise nouvelle découvrable jusqu’à son suivi, sans classement de popularité', function () {
    $owner = person('Entreprise découverte');
    $viewer = person('Lectrice curieuse');
    $company = companyForPublishing($owner);
    app(Publications::class)->publish($owner, PublicationType::Share, [
        'body' => 'Découvrez notre métier de maintenance.',
    ], company: $company);
    Livewire::actingAs($viewer)->test(Fil::class)
        ->call('setFilter', 'decouvrir')->assertSeeText('Découvrez notre métier de maintenance.');
    $this->actingAs($viewer)->post(route('entreprises.follow', $company));
    Livewire::actingAs($viewer)->test(Fil::class)
        ->call('setFilter', 'decouvrir')->assertDontSeeText('Découvrez notre métier de maintenance.');
});

it('réutilise les commentaires et enregistrements des publications sans nouveau réseau social', function () {
    $owner = person('Entreprise collaborative');
    $viewer = person('Personne participante');
    $company = companyForPublishing($owner);
    $post = app(Publications::class)->publish($owner, PublicationType::Share, [
        'body' => 'Échangeons sur les compétences et les métiers.',
    ], company: $company);
    $comment = app(PublicationComments::class)->create($viewer, $post, 'Merci pour votre partage.');
    app(PublicationBookmarks::class)->set($viewer, $post, true);
    expect($comment->body)->toBe('Merci pour votre partage.');
    $this->actingAs($viewer)->get(route('publications.show', $post))
        ->assertOk()->assertSeeText('Merci pour votre partage.')
        ->assertSeeText($company->name)
        ->assertSeeText('Partager');
});

it('fait disparaître immédiatement les publications de toute entreprise suspendue, même par URL directe', function () {
    $owner = person('Entreprise à suspendre');
    $viewer = person('Personne consulte');
    $company = companyForPublishing($owner);
    $post = app(Publications::class)->publish($owner, PublicationType::Need, [
        'body' => 'Opportunité de prestation à suspendre.',
    ], company: $company);

    $this->get(route('publications.show', $post))->assertOk();
    app(CompanyRegistry::class)->review(User::factory()->superAdmin()->create(), $company, 2, 'suspend', 'Usurpation signalée.');

    $this->get(route('publications.show', $post))->assertNotFound();
    $this->actingAs($owner)->get(route('publications.show', $post))->assertNotFound();
    Livewire::actingAs($viewer)->test(Fil::class)->assertDontSeeText('Opportunité de prestation à suspendre.');
    $this->actingAs($viewer)->get(route('entreprises.show', $company))->assertNotFound();
    expect(Company::find($company->id)->verified())->toBeFalse();
});

it('conserve la provenance entreprise après publication illustrée', function () {
    $owner = person('Entreprise images');
    $company = companyForPublishing($owner);
    $this->actingAs($owner)->post(route('entreprises.posts.store', $company), [
        'kind' => 'work',
        'body' => 'Réalisation sanitaire terminée.',
        'media' => UploadedFile::fake()->image('travaux.png', 800, 500),
    ])->assertRedirect();
    $post = Publication::where('company_id', $company->id)->sole();
    expect($post->media_kind)->toBe('image');
    Storage::disk('local')->assertExists($post->media_path);
    $this->get(route('publications.media', $post))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});
