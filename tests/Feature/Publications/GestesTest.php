<?php

use App\Enums\GestureState;
use App\Enums\NeedState;
use App\Enums\PublicationType;
use App\Livewire\Espace;
use App\Livewire\GestureSheet;
use App\Models\Gesture;
use App\Models\Publication;
use App\Models\User;
use App\Services\Gestures;
use App\Services\Publications;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function gestes(): Gestures
{
    return app(Gestures::class);
}

beforeEach(function () {
    $this->auteur = User::factory()->create(['name' => 'Yao Kouassi']);
    $this->aidant = User::factory()->create(['name' => 'Awa Traoré']);
    $this->besoin = publication($this->auteur);
});

it('envoie un geste avec un mot facultatif', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin, '  Je suis libre   samedi. ');

    expect($geste->state)->toBe(GestureState::Sent)
        ->and($geste->message)->toBe('Je suis libre samedi.')
        ->and($geste->user_id)->toBe($this->aidant->id)
        ->and($geste->publication_id)->toBe($this->besoin->id)
        ->and($geste->decided_at)->toBeNull();

    expect(gestes()->propose(User::factory()->create(), $this->besoin)->message)->toBeNull();
    expect(gestes()->propose(User::factory()->create(), $this->besoin, "  \n ")->message)->toBeNull();
});

it('limite le mot à 280 caractères', function () {
    expect(fn () => gestes()->propose($this->aidant, $this->besoin, str_repeat('é', 281)))->toThrow(ValidationException::class);
    expect(Gesture::query()->count())->toBe(0);

    gestes()->propose($this->aidant, $this->besoin, str_repeat('é', 280));
    expect(mb_strlen(Gesture::query()->firstOrFail()->message))->toBe(280);
});

it('n’accepte qu’un geste par personne et par publication', function () {
    gestes()->propose($this->aidant, $this->besoin, 'Premier');

    expect(fn () => gestes()->propose($this->aidant, $this->besoin, 'Second'))->toThrow(AuthorizationException::class)
        ->and(Gesture::query()->count())->toBe(1)
        ->and(Gesture::query()->firstOrFail()->message)->toBe('Premier');

    // Garanti aussi par la base : deux lignes pour la même paire sont impossibles.
    expect(fn () => DB::table('gestures')->insert([
        'id' => (string) Str::uuid(), 'publication_id' => $this->besoin->id, 'user_id' => $this->aidant->id,
        'state' => 'sent', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('permet à une même personne de répondre à des publications différentes', function () {
    $autre = publication($this->auteur);

    gestes()->propose($this->aidant, $this->besoin);
    gestes()->propose($this->aidant, $autre);

    expect(Gesture::query()->where('user_id', $this->aidant->id)->count())->toBe(2);
});

it('refuse à l’auteur de répondre à sa propre publication', function () {
    expect(fn () => gestes()->propose($this->auteur, $this->besoin))->toThrow(AuthorizationException::class)
        ->and(Gesture::query()->count())->toBe(0);
});

it('refuse de répondre à une publication qu’on ne peut pas voir', function () {
    $discrete = publication($this->auteur, data: ['visibility' => 'members']);
    $nonVerifie = User::factory()->unverified()->create();

    expect(fn () => gestes()->propose($nonVerifie, $discrete))->toThrow(AuthorizationException::class);

    $cachee = new Publication(['type' => PublicationType::Need, 'body' => 'x', 'visibility' => 'public', 'need_state' => 'open']);
    $cachee->author()->associate(User::factory()->unverified()->create())->save();
    expect(fn () => gestes()->propose($this->aidant, $cachee))->toThrow(AuthorizationException::class)
        ->and(Gesture::query()->count())->toBe(0);
});

it('refuse à une personne dont l’adresse e-mail n’est pas vérifiée', function () {
    expect(fn () => gestes()->propose(User::factory()->unverified()->create(), $this->besoin))->toThrow(AuthorizationException::class);
});

it('permet de retirer son geste tant qu’il n’est pas accepté', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin);

    gestes()->withdraw($this->aidant, $geste);

    expect($geste->fresh()->state)->toBe(GestureState::Withdrawn);
});

it('ne permet plus de retirer un geste accepté, décliné ou déjà retiré', function () {
    $accepte = gestes()->propose($this->aidant, $this->besoin);
    gestes()->accept($this->auteur, $accepte);
    expect(fn () => gestes()->withdraw($this->aidant, $accepte))->toThrow(AuthorizationException::class);

    $decline = gestes()->propose($autre = User::factory()->create(), $this->besoin);
    gestes()->decline($this->auteur, $decline);
    expect(fn () => gestes()->withdraw($autre, $decline))->toThrow(AuthorizationException::class);

    $retire = gestes()->propose($troisieme = User::factory()->create(), $this->besoin);
    gestes()->withdraw($troisieme, $retire);
    expect(fn () => gestes()->withdraw($troisieme, $retire))->toThrow(AuthorizationException::class);

    expect($accepte->fresh()->state)->toBe(GestureState::Accepted);
});

it('ne permet à personne d’autre de retirer un geste', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin);

    expect(fn () => gestes()->withdraw($this->auteur, $geste))->toThrow(AuthorizationException::class)
        ->and(fn () => gestes()->withdraw(User::factory()->create(), $geste))->toThrow(AuthorizationException::class)
        ->and($geste->fresh()->state)->toBe(GestureState::Sent);
});

it('permet de répondre de nouveau après avoir retiré son geste, sans créer de seconde ligne', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin, 'Premier mot');
    gestes()->withdraw($this->aidant, $geste);

    $nouveau = gestes()->propose($this->aidant, $this->besoin, 'Nouveau mot');

    expect($nouveau->id)->toBe($geste->id)
        ->and(Gesture::query()->count())->toBe(1)
        ->and($nouveau->fresh()->state)->toBe(GestureState::Sent)
        ->and($nouveau->fresh()->message)->toBe('Nouveau mot');
});

it('ne permet pas de répondre de nouveau après avoir été décliné', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin);
    gestes()->decline($this->auteur, $geste);

    expect(fn () => gestes()->propose($this->aidant, $this->besoin))->toThrow(AuthorizationException::class);
});

it('ne permet pas de répondre à un besoin résolu ou fermé, mais bien à un savoir', function () {
    $resolu = publication($this->auteur);
    $ferme = publication($this->auteur);
    app(Publications::class)->resolve($this->auteur, $resolu);
    app(Publications::class)->close($this->auteur, $ferme);

    expect(fn () => gestes()->propose($this->aidant, $resolu))->toThrow(AuthorizationException::class)
        ->and(fn () => gestes()->propose($this->aidant, $ferme))->toThrow(AuthorizationException::class);

    $savoir = publication($this->auteur, PublicationType::Knowledge);
    expect(gestes()->propose($this->aidant, $savoir)->state)->toBe(GestureState::Sent);
});

it('fait passer un besoin d’« ouvert » à « en cours » dès qu’un geste est accepté', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin);
    expect($this->besoin->fresh()->need_state)->toBe(NeedState::Open);

    gestes()->accept($this->auteur, $geste);

    expect($geste->fresh()->state)->toBe(GestureState::Accepted)
        ->and($geste->fresh()->decided_at)->not->toBeNull()
        ->and($this->besoin->fresh()->need_state)->toBe(NeedState::InProgress);
});

it('permet d’accepter plusieurs gestes ; le besoin reste « en cours »', function () {
    $un = gestes()->propose($this->aidant, $this->besoin);
    $deux = gestes()->propose(User::factory()->create(), $this->besoin);

    gestes()->accept($this->auteur, $un);
    gestes()->accept($this->auteur, $deux);

    expect(Gesture::query()->where('state', 'accepted')->count())->toBe(2)
        ->and($this->besoin->fresh()->need_state)->toBe(NeedState::InProgress);
});

it('ne change pas l’état d’un besoin quand on décline un geste', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin);

    gestes()->decline($this->auteur, $geste);

    expect($geste->fresh()->state)->toBe(GestureState::Declined)
        ->and($geste->fresh()->decided_at)->not->toBeNull()
        ->and($this->besoin->fresh()->need_state)->toBe(NeedState::Open);
});

it('accepte un geste sur un savoir sans lui donner d’état', function () {
    $savoir = publication($this->auteur, PublicationType::Knowledge);
    $geste = gestes()->propose($this->aidant, $savoir);

    gestes()->accept($this->auteur, $geste);

    expect($geste->fresh()->state)->toBe(GestureState::Accepted)->and($savoir->fresh()->need_state)->toBeNull();
});

it('réserve l’acceptation et le refus à l’auteur de la publication', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin);

    foreach ([$this->aidant, User::factory()->create(), User::factory()->moderator()->create(), User::factory()->superAdmin()->create()] as $autre) {
        expect(fn () => gestes()->accept($autre, $geste))->toThrow(AuthorizationException::class)
            ->and(fn () => gestes()->decline($autre, $geste))->toThrow(AuthorizationException::class);
    }

    expect($geste->fresh()->state)->toBe(GestureState::Sent)->and($this->besoin->fresh()->need_state)->toBe(NeedState::Open);
});

it('ne décide d’un geste qu’une fois : ni accepter deux fois, ni décliner un geste accepté', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin);
    gestes()->accept($this->auteur, $geste);

    expect(fn () => gestes()->accept($this->auteur, $geste))->toThrow(AuthorizationException::class)
        ->and(fn () => gestes()->decline($this->auteur, $geste))->toThrow(AuthorizationException::class)
        ->and($geste->fresh()->state)->toBe(GestureState::Accepted);
});

it('ne permet pas de décider d’un geste retiré', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin);
    gestes()->withdraw($this->aidant, $geste);

    expect(fn () => gestes()->accept($this->auteur, $geste))->toThrow(AuthorizationException::class)
        ->and($this->besoin->fresh()->need_state)->toBe(NeedState::Open);
});

it('ne permet pas d’accepter un geste quand le besoin est résolu ou fermé', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin);
    app(Publications::class)->close($this->auteur, $this->besoin);

    expect(fn () => gestes()->accept($this->auteur, $geste))->toThrow(AuthorizationException::class)
        ->and(fn () => gestes()->decline($this->auteur, $geste))->toThrow(AuthorizationException::class)
        ->and($geste->fresh()->state)->toBe(GestureState::Sent);
});

it('limite le nombre de gestes par heure et par jour, par personne', function () {
    config(['gamad.limits.gestures_per_hour' => 2, 'gamad.limits.gestures_per_day' => 3]);

    gestes()->propose($this->aidant, publication(User::factory()->create()));
    gestes()->propose($this->aidant, publication(User::factory()->create()));
    expect(fn () => gestes()->propose($this->aidant, publication(User::factory()->create())))->toThrow(ValidationException::class);

    gestes()->propose(User::factory()->create(), $this->besoin);
    expect(Gesture::query()->count())->toBe(3);
});

it('ouvre la feuille « Je peux aider », envoie le geste avec un mot et prévient la page', function () {
    Livewire::actingAs($this->aidant)->test(GestureSheet::class)
        ->assertDontSeeText('Que proposez-vous concrètement ?')
        ->dispatch('open-gesture', id: $this->besoin->id)
        ->assertSet('publicationId', $this->besoin->id)
        ->assertSeeText('Je peux aider')
        ->assertSeeText('Yao recevra votre proposition et pourra l’accepter.')
        ->assertSeeText('Que proposez-vous concrètement ? (facultatif)')
        ->assertSeeText('Jamais votre e-mail ni votre téléphone.')
        ->assertSeeText('Vous pourrez retirer votre proposition tant qu’elle n’est pas acceptée.')
        ->set('message', 'Je suis libre samedi.')
        ->call('send')
        ->assertHasNoErrors()
        ->assertDispatched('gesture-changed')
        ->assertSet('publicationId', null);

    expect(Gesture::query()->firstOrFail()->message)->toBe('Je suis libre samedi.');
});

it('refuse un mot trop long dans la feuille, et le dit', function () {
    Livewire::actingAs($this->aidant)->test(GestureSheet::class)
        ->dispatch('open-gesture', id: $this->besoin->id)
        ->set('message', str_repeat('a', 281))
        ->call('send')
        ->assertHasErrors(['message'])
        ->assertSeeText('Un mot ne peut pas dépasser 280 caractères.');

    expect(Gesture::query()->count())->toBe(0);
});

it('annule la feuille sans rien envoyer', function () {
    Livewire::actingAs($this->aidant)->test(GestureSheet::class)
        ->dispatch('open-gesture', id: $this->besoin->id)->call('close')->assertSet('publicationId', null);

    expect(Gesture::query()->count())->toBe(0);
});

it('refuse d’ouvrir la feuille sur sa propre publication, sur une publication close ou falsifiée', function () {
    Livewire::actingAs($this->auteur)->test(GestureSheet::class)->dispatch('open-gesture', id: $this->besoin->id)->assertForbidden();

    $ferme = publication($this->auteur);
    app(Publications::class)->close($this->auteur, $ferme);
    Livewire::actingAs($this->aidant)->test(GestureSheet::class)->dispatch('open-gesture', id: $ferme->id)->assertForbidden();

    Livewire::actingAs($this->aidant)->test(GestureSheet::class)->dispatch('open-gesture', id: 'garbage')->assertNotFound();
    expect(fn () => Livewire::actingAs($this->aidant)->test(GestureSheet::class)->dispatch('open-gesture', id: (string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});

it('refuse d’envoyer depuis la feuille si on n’est pas connecté', function () {
    Livewire::test(GestureSheet::class)->dispatch('open-gesture', id: $this->besoin->id)->assertForbidden();
    expect(Gesture::query()->count())->toBe(0);
});

it('retire son geste depuis la page, et seulement le sien', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin);

    // Une autre personne n'a aucun geste sur cette publication : il n'y a rien à retirer, et le geste d'autrui reste intact.
    expect(fn () => Livewire::actingAs(User::factory()->create())->test(GestureSheet::class)->dispatch('withdraw-gesture', id: $this->besoin->id))
        ->toThrow(ModelNotFoundException::class);
    expect($geste->fresh()->state)->toBe(GestureState::Sent);

    Livewire::actingAs($this->aidant)->test(GestureSheet::class)->dispatch('withdraw-gesture', id: $this->besoin->id)->assertDispatched('gesture-changed');
    expect($geste->fresh()->state)->toBe(GestureState::Withdrawn);
});

it('affiche à l’auteur le nom, la ville, les capacités et le mot de la personne qui répond, jamais son e-mail', function () {
    $aidant = person('Awa Traoré', ['Informatique', 'Tableur Excel'], city: 'Yamoussoukro', attributes: ['email' => 'awa.secret@example.test']);
    gestes()->propose($aidant, $this->besoin, 'Je fais ce travail régulièrement.');

    $this->actingAs($this->auteur)->get(route('publications.show', $this->besoin))->assertOk()
        ->assertSeeText('Awa Traoré')->assertSeeText('Yamoussoukro')->assertSeeText('Informatique')->assertSeeText('Tableur Excel')
        ->assertSeeText('Je fais ce travail régulièrement.')
        ->assertDontSee('awa.secret@example.test')->assertDontSee('mailto:')->assertDontSee('tel:');
});

it('supprime les publications et les gestes avec le compte (D-088)', function () {
    $geste = gestes()->propose($this->aidant, $this->besoin);

    // Le compte de l'aidant : son geste disparaît, la publication de l'auteur reste.
    Livewire::actingAs($this->aidant)->test(Espace::class)->set('password', 'password')->call('deleteAccount');
    expect(Gesture::query()->count())->toBe(0)->and(Publication::query()->count())->toBe(1);

    // Le compte de l'auteur : sa publication et les gestes reçus disparaissent.
    $autre = User::factory()->create();
    gestes()->propose($autre, $this->besoin);
    Livewire::actingAs($this->auteur)->test(Espace::class)->set('password', 'password')->call('deleteAccount');

    expect(Publication::query()->count())->toBe(0)->and(Gesture::query()->count())->toBe(0)->and(DB::table('publication_skill')->count())->toBe(0);
});
