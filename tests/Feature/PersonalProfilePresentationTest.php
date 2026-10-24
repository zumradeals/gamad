<?php

use App\Enums\PublicationType;
use App\Livewire\ModifierProfil;
use App\Models\Conversation;
use App\Models\Publication;
use App\Models\User;
use App\Services\Moderation;
use App\Services\People;
use App\Services\ProfileDetails;
use App\Services\ProfileEditor;
use App\Services\Publications;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    config(['gamad.initial_experience' => true]);
    $this->artisan = person('Moussa Koné', ['Plomberie'], ['Électricité']);
    $this->visitor = person('Awa');
});

it('enregistre un intitulé et une couverture choisie sans diplôme ni copie de fichier', function () {
    $post = publication($this->artisan, PublicationType::PersonalWork, ['body' => 'Une installation sanitaire']);
    $post->update(['media_kind' => 'image', 'media_path' => 'publication-media/test.jpg']);
    Livewire::actingAs($this->artisan)->test(ModifierProfil::class)
        ->set('headline', " Plombier\n · Installation et dépannage ")
        ->set('cover_publication_id', $post->id)->call('save')->assertHasNoErrors();
    $profile = $this->artisan->profile->fresh();
    expect($profile->headline)->toBe('Plombier · Installation et dépannage')
        ->and($profile->cover_publication_id)->toBe($post->id);
    $this->actingAs($this->visitor)->get(route('personnes.show', $profile))->assertOk()
        ->assertSeeText('Plombier · Installation et dépannage')
        ->assertSee('alt="Couverture choisie par Moussa Koné"', false)
        ->assertSee(route('publications.media', $post), false);
});

it('refuse une couverture étrangère masquée sans image ou non personnelle et les intitulés trop longs', function () {
    $foreign = publication($this->visitor, PublicationType::PersonalWork);
    $foreign->update(['media_kind' => 'image', 'media_path' => 'publication-media/foreign.jpg']);
    $hidden = publication($this->artisan, PublicationType::PersonalWork);
    $hidden->update(['media_kind' => 'image', 'media_path' => 'publication-media/hidden.jpg']);
    app(Moderation::class)->hide(User::factory()->superAdmin()->create(), $hidden, 'Masquage pour vérifier la confidentialité de la couverture.');
    $text = publication($this->artisan, PublicationType::Share);
    $document = publication($this->artisan, PublicationType::Share);
    $document->update(['media_kind' => 'pdf', 'media_path' => 'publication-media/document.pdf']);
    foreach ([$foreign, $hidden, $text, $document] as $post) {
        expect(fn () => app(ProfileDetails::class)->clean($this->artisan, ['cover_publication_id' => $post->id], ['Plomberie']))->toThrow(ValidationException::class);
    }
    expect(fn () => app(ProfileDetails::class)->clean($this->artisan, ['cover_publication_id' => 'invalid'], ['Plomberie']))->toThrow(ValidationException::class);
    expect(fn () => app(ProfileDetails::class)->clean($this->artisan, ['headline' => str_repeat('a', 101)], ['Plomberie']))->toThrow(ValidationException::class);
    // Les publications collectives sont exclues même lorsque l’auteur les gère.
    config(['gamad.initial_experience' => false]);
    $group = zumra($this->artisan);
    $collective = app(Publications::class)->publish($this->artisan, PublicationType::Knowledge, ['body' => 'Savoir collectif'], $group);
    $collective->update(['media_kind' => 'image', 'media_path' => 'publication-media/group.jpg']);
    expect(fn () => app(ProfileDetails::class)->clean($this->artisan, ['cover_publication_id' => $collective->id], ['Plomberie']))->toThrow(ValidationException::class);
});

it('réévalue la visibilité de la couverture et des réalisations à chaque visite', function () {
    $post = publication($this->artisan, PublicationType::PersonalWork, ['body' => 'Une réalisation réservée', 'visibility' => Publication::MEMBERS]);
    $post->update(['media_kind' => 'image', 'media_path' => 'publication-media/reserved.jpg']);
    $profile = $this->artisan->profile;
    $profile->update(['cover_publication_id' => $post->id, 'featured_publications' => [$post->id]]);
    auth()->logout();
    $this->get(route('personnes.show', $profile))->assertOk()->assertDontSee(route('publications.media', $post), false)->assertDontSeeText('Une réalisation réservée');
    $this->actingAs($this->visitor)->get(route('personnes.show', $profile))->assertOk()->assertSee('alt="Couverture choisie par Moussa Koné"', false);
    app(Moderation::class)->hide(User::factory()->superAdmin()->create(), $post, 'Masquage pour vérifier la confidentialité de la couverture.');
    $this->get(route('personnes.show', $profile))->assertOk()->assertDontSee(route('publications.media', $post), false)->assertDontSeeText('Une réalisation réservée');
});

it('retire la référence de couverture lors de la suppression de la publication', function () {
    $post = publication($this->artisan, PublicationType::PersonalWork);
    $this->artisan->profile->update(['cover_publication_id' => $post->id]);
    $post->delete();
    expect($this->artisan->profile->fresh()->cover_publication_id)->toBeNull();
    $this->get(route('personnes.show', $this->artisan->profile))->assertOk()->assertDontSee('alt="Couverture choisie', false);
});

it('conserve les nouveaux champs si un ancien appel de service les omet et permet de les retirer explicitement', function () {
    $post = publication($this->artisan, PublicationType::PersonalWork);
    $post->update(['media_kind' => 'image', 'media_path' => 'publication-media/own.jpg']);
    $this->artisan->profile->update(['headline' => 'Artisan plombier', 'cover_publication_id' => $post->id]);
    $data = ['name' => $this->artisan->name, 'bio' => '', 'visibility' => 'public', 'country_id' => null, 'city_id' => null, 'offered' => ['Plomberie'], 'wanted' => [], 'domain_ids' => []];
    app(ProfileEditor::class)->save($this->artisan, $data);
    expect($this->artisan->profile->fresh()->headline)->toBe('Artisan plombier')->and($this->artisan->profile->fresh()->cover_publication_id)->toBe($post->id);
    app(ProfileEditor::class)->save($this->artisan, $data + ['headline' => '', 'cover_publication_id' => '']);
    expect($this->artisan->profile->fresh()->headline)->toBeNull()->and($this->artisan->profile->fresh()->cover_publication_id)->toBeNull();
});

it('présente les savoir-faire et réalisations avant le parcours et les publications sans exposer les préférences privées', function () {
    $post = publication($this->artisan, PublicationType::PersonalWork, ['body' => 'Un chantier concret']);
    $this->artisan->profile->update(['headline' => '<script>alert(1)</script>', 'matching_preferences' => ['note' => 'Disponibilité strictement privée', 'zones' => ['Localité privée']], 'experiences' => [['title' => 'Apprentissage en atelier', 'kind' => 'learning', 'description' => 'Auprès d’un artisan.']]]);
    $response = $this->actingAs($this->visitor)->get(route('personnes.show', $this->artisan->profile))->assertOk()
        ->assertSeeInOrder(['id="savoir-faire"', 'id="realisations"', 'id="parcours"', 'id="apprend"', 'id="publications"'], false)
        ->assertSeeText('Apprentissage en atelier')->assertDontSeeText('Disponibilité strictement privée')->assertDontSeeText('Localité privée')
        ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    expect($response->viewData('showcasePosts')->pluck('id')->all())->toBe([$post->id]);
});

it('respecte la sélection à la une même hors des vingt dernières publications', function () {
    $selected = publication($this->artisan, PublicationType::Share, ['body' => 'Mon savoir-faire sélectionné']);
    $selected->update(['created_at' => now()->subMonth()]);
    foreach (range(1, 21) as $i) {
        publication($this->artisan, PublicationType::PersonalWork, ['body' => 'Une réalisation '.$i]);
    }
    $this->artisan->profile->update(['featured_publications' => [$selected->id]]);
    $response = $this->get(route('personnes.show', $this->artisan->profile))->assertOk()->assertSeeText('À la une')->assertSeeText('Mon savoir-faire sélectionné');
    expect($response->viewData('showcasePosts')->pluck('id')->all())->toBe([$selected->id]);
});

it('ne met plus en avant une offre clôturée ou expirée et conserve son historique', function () {
    $post = publication($this->artisan, PublicationType::Offer, ['body' => 'Une offre visible']);
    $response = $this->get(route('personnes.show', $this->artisan->profile))->assertOk();
    expect($response->viewData('currentOffer')->id)->toBe($post->id);
    foreach ([['enabled' => true, 'open' => false], ['enabled' => true, 'open' => true, 'until' => today()->subDay()->toDateString()]] as $matching) {
        $post->update(['matching' => $matching]);
        $response = $this->get(route('personnes.show', $this->artisan->profile))->assertOk()->assertSeeText('Une offre visible');
        expect($response->viewData('currentOffer'))->toBeNull();
    }
});

it('ne transforme pas le profil en accès direct à une conversation et conserve le blocage', function () {
    $this->actingAs($this->visitor)->get(route('personnes.show', $this->artisan->profile))->assertOk()->assertSeeText('Entrer en relation')->assertDontSeeText('Écrire');
    expect(Conversation::count())->toBe(0);
    app(People::class)->block($this->artisan, $this->visitor, true);
    $this->get(route('personnes.show', $this->artisan->profile))->assertNotFound();
});
