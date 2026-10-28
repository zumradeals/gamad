<?php

use App\Enums\NeedState;
use App\Livewire\PagePublication;
use App\Livewire\ProfileGratitudes;
use App\Models\Gratitude;
use App\Models\User;
use App\Services\Gestures;
use App\Services\Gratitudes;
use App\Services\Publications;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->auteur = person('Yao Kouassi', ['Couture']);
    $this->aidant = person('Awa Traoré', ['Informatique']);
    $this->autre = person('Seydou Bamba', ['Informatique']);
    $this->besoin = publication($this->auteur, data: ['body' => 'Logiciel de caisse pour ma boutique']);
    conversationDe($this->auteur, $this->aidant, $this->besoin);
});

function remerciements(): Gratitudes
{
    return app(Gratitudes::class);
}

function pageBesoin($besoin, $qui)
{
    return Livewire::actingAs($qui)->test(PagePublication::class, ['publication' => $besoin]);
}

// ─── Marquer résolu et remercier ─────────────────────────────────────────────

it('marque le besoin résolu et publie le remerciement, ensemble', function () {
    $g = remerciements()->resolveAndThank($this->auteur, $this->besoin, $this->aidant->id, 'Awa a installé le logiciel et m’a formé en une matinée.');

    expect($this->besoin->fresh()->need_state)->toBe(NeedState::Resolved)
        ->and($this->besoin->fresh()->resolved_at)->not->toBeNull()
        ->and($g->helper_id)->toBe($this->aidant->id)->and($g->author_id)->toBe($this->auteur->id)
        ->and($g->hidden_at)->toBeNull();
});

it('ne marque pas le besoin résolu si le remerciement est refusé : tout ou rien', function () {
    // Awa a été acceptée, pas Seydou (aucun geste) : le choix est refusé et le besoin reste en cours.
    expect(fn () => remerciements()->resolveAndThank($this->auteur, $this->besoin, $this->autre->id, 'Merci'))->toThrow(ValidationException::class)
        ->and(fn () => remerciements()->resolveAndThank($this->auteur, $this->besoin, $this->aidant->id, ''))->toThrow(ValidationException::class);

    expect($this->besoin->fresh()->need_state)->toBe(NeedState::InProgress)->and(Gratitude::count())->toBe(0);
});

it('ne remercie qu’une personne dont la proposition a été acceptée', function () {
    $enAttente = geste($this->autre, $this->besoin);
    $decline = User::factory()->create();
    app(Gestures::class)->decline($this->auteur, geste($decline, $this->besoin));
    app(Publications::class)->resolve($this->auteur, $this->besoin);

    foreach ([$this->autre->id, $decline->id, $this->auteur->id, 'pas-un-identifiant', (string) Str::uuid()] as $choisi) {
        expect(fn () => remerciements()->thank($this->auteur, $this->besoin->fresh(), $choisi, 'Merci'))->toThrow(ValidationException::class);
    }

    expect(Gratitude::count())->toBe(0);
});

it('accepte 280 caractères et refuse 281 (D-084)', function () {
    app(Publications::class)->resolve($this->auteur, $this->besoin);

    expect(fn () => remerciements()->thank($this->auteur, $this->besoin->fresh(), $this->aidant->id, str_repeat('é', 281)))->toThrow(ValidationException::class);

    remerciements()->thank($this->auteur, $this->besoin->fresh(), $this->aidant->id, str_repeat('é', 280));
    expect(Gratitude::count())->toBe(1);
});

it('réserve le remerciement à l’auteur du besoin', function () {
    app(Publications::class)->resolve($this->auteur, $this->besoin);

    foreach ([$this->aidant, $this->autre, User::factory()->moderator()->create()] as $qui) {
        expect(fn () => remerciements()->thank($qui, $this->besoin->fresh(), $this->aidant->id, 'Je me remercie'))->toThrow(AuthorizationException::class);
    }

    expect(Gratitude::count())->toBe(0);
});

it('ne permet de remercier qu’un besoin résolu, et une seule fois', function () {
    expect(fn () => remerciements()->thank($this->auteur, $this->besoin, $this->aidant->id, 'Trop tôt'))->toThrow(AuthorizationException::class);

    app(Publications::class)->close($this->auteur, $this->besoin);
    expect(fn () => remerciements()->thank($this->auteur, $this->besoin->fresh(), $this->aidant->id, 'Fermé'))->toThrow(AuthorizationException::class);
});

it('n’accepte qu’un remerciement par besoin', function () {
    conversationDe($this->auteur, $this->autre, $this->besoin);
    remerciements()->resolveAndThank($this->auteur, $this->besoin, $this->aidant->id, 'Merci Awa');

    expect(fn () => remerciements()->thank($this->auteur, $this->besoin->fresh(), $this->autre->id, 'Merci Seydou'))->toThrow(AuthorizationException::class)
        ->and(Gratitude::count())->toBe(1);
});

it('permet de marquer résolu sans remerciement, puis de remercier plus tard', function () {
    pageBesoin($this->besoin, $this->auteur)->call('resolve');

    expect($this->besoin->fresh()->need_state)->toBe(NeedState::Resolved)->and(Gratitude::count())->toBe(0);

    pageBesoin($this->besoin->fresh(), $this->auteur)
        ->assertSeeText('Remercier une personne')
        ->set('helperId', $this->aidant->id)->set('gratitudeText', 'Merci Awa')->call('thank')->assertHasNoErrors()
        ->assertSeeText('Votre remerciement')->assertSeeText('Merci Awa');

    expect(Gratitude::count())->toBe(1);
});

it('remercie depuis l’écran du besoin : « Marquer résolu et remercier »', function () {
    pageBesoin($this->besoin, $this->auteur)
        ->assertSeeText('Qui vous a aidé ?')->assertSeeText('Awa Traoré')->assertSeeText('Proposition acceptée')->assertSeeText('Quelqu’un d’autre ou personne en particulier')
        ->set('helperId', $this->aidant->id)->set('gratitudeText', 'Awa a installé le logiciel.')->call('resolveAndThank')->assertHasNoErrors();

    expect($this->besoin->fresh()->need_state)->toBe(NeedState::Resolved)->and(Gratitude::sole()->body)->toBe('Awa a installé le logiciel.');
});

it('affiche les erreurs du remerciement sur l’écran et ne résout pas le besoin', function () {
    pageBesoin($this->besoin, $this->auteur)->set('helperId', $this->aidant->id)->set('gratitudeText', '')->call('resolveAndThank')->assertHasErrors(['gratitudeText']);
    pageBesoin($this->besoin, $this->auteur)->set('helperId', $this->autre->id)->set('gratitudeText', 'Merci')->call('resolveAndThank')->assertHasErrors(['helper']);

    expect($this->besoin->fresh()->need_state)->toBe(NeedState::InProgress);
});

it('ne propose pas de remerciement quand aucune proposition n’a été acceptée', function () {
    $sansAide = publication($this->auteur, data: ['body' => 'Un autre besoin']);
    geste($this->autre, $sansAide);

    pageBesoin($sansAide, $this->auteur)->assertDontSeeText('Qui vous a aidé ?')->call('resolve');
    pageBesoin($sansAide->fresh(), $this->auteur)->assertDontSeeText('Remercier une personne');
});

it('ne laisse pas un autre que l’auteur appeler les actions de remerciement', function () {
    // Livewire traduit le refus du serveur en réponse 403.
    pageBesoin($this->besoin, $this->aidant)->set('helperId', $this->aidant->id)->set('gratitudeText', 'Merci à moi')->call('resolveAndThank')->assertForbidden();

    expect($this->besoin->fresh()->need_state)->toBe(NeedState::InProgress)->and(Gratitude::count())->toBe(0);
});

// ─── Sur le profil de la personne qui a aidé ─────────────────────────────────

it('affiche le remerciement sur le profil de la personne qui a aidé, sans compteur ni note', function () {
    remerciements()->resolveAndThank($this->auteur, $this->besoin, $this->aidant->id, 'Awa a installé le logiciel et m’a formé en une matinée.');

    $html = $this->get(route('personnes.show', $this->aidant->profile->public_id))->assertOk()
        ->assertSeeText('Remerciements reçus')->assertSeeText('Awa a installé le logiciel et m’a formé en une matinée.')
        ->assertSeeText('Yao Kouassi')->assertSeeText('Logiciel de caisse')->getContent();

    // Un lien vers la personne qui remercie : son profil est public.
    expect($html)->toContain(route('personnes.show', $this->auteur->profile->public_id))
        ->and($html)->not->toMatch('/\d+\s+remerciements?/i');
});

it('n’affiche aucune section « Remerciements reçus » à un visiteur quand il n’y en a pas', function () {
    $this->get(route('personnes.show', $this->aidant->profile->public_id))->assertOk()->assertDontSeeText('Remerciements');
});

it('montre à la personne remerciée sa section, même vide, pour qu’elle comprenne', function () {
    $this->actingAs($this->aidant)->get(route('personnes.show', $this->aidant->profile->public_id))
        ->assertSeeText('Remerciements reçus')->assertSeeText('Aucun remerciement pour l’instant');
});

it('permet à la personne remerciée de masquer le remerciement de son profil, puis de le rétablir', function () {
    $g = remerciements()->resolveAndThank($this->auteur, $this->besoin, $this->aidant->id, 'Merci Awa, vraiment.');
    $profil = $this->aidant->profile;

    Livewire::actingAs($this->aidant)->test(ProfileGratitudes::class, ['profile' => $profil])
        ->assertSeeText('Merci Awa, vraiment.')->call('hide', $g->id)->assertSeeText('Masqué')->assertSeeText('Afficher à nouveau');

    // Les autres ne le voient plus (visiteur sans compte et personne connectée).
    app('auth')->forgetGuards();
    $this->get(route('personnes.show', $profil->public_id))->assertDontSeeText('Merci Awa, vraiment.');
    $this->actingAs(User::factory()->create())->get(route('personnes.show', $profil->public_id))->assertDontSeeText('Merci Awa, vraiment.');

    Livewire::actingAs($this->aidant)->test(ProfileGratitudes::class, ['profile' => $profil])->call('show', $g->id)->assertSeeText('Masquer de mon profil');
    $this->actingAs(User::factory()->create())->get(route('personnes.show', $profil->public_id))->assertSeeText('Merci Awa, vraiment.');
});

it('réserve le masquage à la personne remerciée', function () {
    $g = remerciements()->resolveAndThank($this->auteur, $this->besoin, $this->aidant->id, 'Merci');
    $profil = $this->aidant->profile;

    foreach ([$this->auteur, $this->autre] as $qui) {
        Livewire::actingAs($qui)->test(ProfileGratitudes::class, ['profile' => $profil])->call('hide', $g->id)->assertForbidden();
    }

    expect(fn () => remerciements()->setHidden($this->auteur, $g, true))->toThrow(AuthorizationException::class)
        ->and($g->fresh()->hidden_at)->toBeNull();
});

it('ne laisse pas masquer le remerciement d’un autre profil en changeant l’identifiant', function () {
    $g = remerciements()->resolveAndThank($this->auteur, $this->besoin, $this->aidant->id, 'Merci');
    $profilAutre = $this->autre->profile;

    // Seydou ouvre son propre profil, mais vise le remerciement d'Awa : introuvable pour lui.
    expect(fn () => Livewire::actingAs($this->autre)->test(ProfileGratitudes::class, ['profile' => $profilAutre])->call('hide', $g->id))
        ->toThrow(ModelNotFoundException::class);
    expect($g->fresh()->hidden_at)->toBeNull();
});

it('verrouille le profil du composant et revérifie le droit de le voir à l’affichage', function () {
    $discret = person('Chantal Discrète', ['Couture'], visibility: 'members');
    $profilDiscret = $discret->profile;

    $composant = Livewire::test(ProfileGratitudes::class, ['profile' => $this->aidant->profile]);
    expect(fn () => $composant->set('profileId', $profilDiscret->id))->toThrow(CannotUpdateLockedPropertyException::class);

    // Un visiteur sans compte n'obtient jamais les remerciements d'un profil « inscrits seulement ».
    $page = new ProfileGratitudes;
    $page->profileId = $profilDiscret->id;
    app('auth')->forgetGuards();

    try {
        $page->render();
        $this->fail('Rendu autorisé pour un visiteur');
    } catch (AuthorizationException $e) {
        expect($e->status())->toBe(404);
    }
});

it('ne montre pas, aux personnes qui ne peuvent pas la voir, l’extrait d’une publication « inscrits seulement »', function () {
    $privee = publication($this->auteur, data: ['body' => 'Besoin réservé aux inscrits, texte secret', 'visibility' => 'members']);
    conversationDe($this->auteur, $this->aidant, $privee);
    remerciements()->resolveAndThank($this->auteur, $privee, $this->aidant->id, 'Merci pour ton aide');
    $profil = $this->aidant->profile;

    // Visiteur : le remerciement est public, mais pas le texte du besoin privé.
    $this->get(route('personnes.show', $profil->public_id))->assertSeeText('Merci pour ton aide')->assertDontSeeText('texte secret');
    // Personne inscrite et vérifiée : elle peut voir le besoin.
    $this->actingAs(User::factory()->create())->get(route('personnes.show', $profil->public_id))->assertSeeText('texte secret');
});

it('ne montre pas le remerciement d’une personne dont l’adresse e-mail n’est plus vérifiée', function () {
    remerciements()->resolveAndThank($this->auteur, $this->besoin, $this->aidant->id, 'Merci Awa');
    $this->auteur->forceFill(['email_verified_at' => null])->save();

    $this->get(route('personnes.show', $this->aidant->profile->public_id))->assertDontSeeText('Merci Awa');
});

it('retire le remerciement avec le besoin ou la personne remerciée supprimant son compte', function () {
    remerciements()->resolveAndThank($this->auteur, $this->besoin, $this->aidant->id, 'Merci');
    expect(Gratitude::count())->toBe(1);

    fermerCompte($this->aidant);
    expect(Gratitude::count())->toBe(0);
});
