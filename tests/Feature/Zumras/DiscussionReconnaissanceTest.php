<?php

use App\Enums\NotificationKind;
use App\Enums\PublicationType;
use App\Enums\ZumraRole;
use App\Livewire\CarrefourZumras;
use App\Livewire\MondeZumra;
use App\Models\AdminAction;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\ZumraPost;
use App\Services\Memberships;
use App\Services\Publications;
use App\Services\ZumraPosts;
use App\Services\Zumras;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->yao = person('Yao Kouassi', ['Couture']);
    $this->zumra = zumra($this->yao, 'Couture solidaire');
    $this->resp = person('Responsable Un', ['Broderie']);
    $this->membre = person('Simple Membre', ['Tissage'], ['Comptabilité']);
    $this->dehors = person('Personne Extérieure', ['Menuiserie']);
    $this->president = User::factory()->superAdmin()->create(['name' => 'Le Président']);
    ajouteMembre($this->zumra, $this->resp, ZumraRole::Manager);
    ajouteMembre($this->zumra, $this->membre);
});

// ─── Discussion ──────────────────────────────────────────────────────────────

it('laisse les membres lire et écrire dans la Discussion, dans l’ordre où les messages ont été écrits', function () {
    app(ZumraPosts::class)->post($this->yao, $this->zumra, 'Premier message');

    monde($this->membre, $this->zumra, 'discussion')->assertSeeText('Premier message')->assertSeeText('Yao Kouassi')
        ->set('postBody', 'Réponse du membre')->call('sendPost')->assertHasNoErrors()->assertSet('postBody', '')
        ->assertSeeText('Réponse du membre');

    $html = monde($this->membre, $this->zumra, 'discussion')->html();
    expect(strpos($html, 'Premier message'))->toBeLessThan(strpos($html, 'Réponse du membre'));

    expect(ZumraPost::count())->toBe(2);
});

it('cache la Discussion à qui n’est pas membre : onglet absent, page introuvable, aucun envoi', function () {
    app(ZumraPosts::class)->post($this->yao, $this->zumra, 'Message secret');

    foreach ([$this->dehors, User::factory()->unverified()->create()] as $autre) {
        monde($this->dehors, $this->zumra, 'formation')->assertDontSeeText('Discussion')->assertDontSeeText('Message secret');
        $this->actingAs($autre)->get(route('zumras.show', $this->zumra).'?onglet=discussion')->assertNotFound();
        expect(fn () => app(ZumraPosts::class)->post($autre, $this->zumra, 'Intrus'))->toThrow(AuthorizationException::class);
    }

    app('auth')->forgetGuards();
    $this->get(route('zumras.show', $this->zumra).'?onglet=discussion')->assertNotFound();
    expect(ZumraPost::count())->toBe(1);
});

it('refuse un message vide ou de plus de 1000 caractères, et accepte 1000', function () {
    foreach (['', "  \n ", str_repeat('é', 1001)] as $mauvais) {
        expect(fn () => app(ZumraPosts::class)->post($this->membre, $this->zumra, $mauvais))->toThrow(ValidationException::class);
    }
    expect(app(ZumraPosts::class)->post($this->membre, $this->zumra, str_repeat('é', 1000))->body)->toHaveLength(1000);
    expect(ZumraPost::count())->toBe(1);
});

it('garde le texte brut : rien n’est interprété et les adresses ne sont pas cliquables', function () {
    app(ZumraPosts::class)->post($this->membre, $this->zumra, "<b>gras</b> https://exemple.org\nligne 2");

    $html = monde($this->yao, $this->zumra, 'discussion')->html();

    expect($html)->toContain('&lt;b&gt;gras&lt;/b&gt;')->and($html)->not->toContain('<a href="https://exemple.org');
});

it('limite le débit des messages par minute et par jour', function () {
    config(['gamad.limits.zumra_posts_per_minute' => 2, 'gamad.limits.zumra_posts_per_day' => 3]);
    RateLimiter::clear('x');

    app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Un');
    app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Deux');
    expect(fn () => app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Trois'))->toThrow(ValidationException::class, 'trop vite');

    // La limite est par personne.
    app(ZumraPosts::class)->post($this->resp, $this->zumra, 'Autre personne');
    expect(ZumraPost::count())->toBe(3);

    $this->travel(2)->minutes();
    app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Trois');
    $this->travel(2)->minutes();
    expect(fn () => app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Quatre'))->toThrow(ValidationException::class, 'aujourd’hui');
});

it('laisse chacun supprimer ses messages, et les responsables supprimer ceux des autres, pas un simple membre', function () {
    $duMembre = app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Message du membre');
    $duResp = app(ZumraPosts::class)->post($this->resp, $this->zumra, 'Message du responsable');
    $autreMembre = person('Autre Membre', []);
    ajouteMembre($this->zumra, $autreMembre);

    expect(fn () => app(ZumraPosts::class)->delete($autreMembre, $duMembre))->toThrow(HttpException::class);
    expect(fn () => app(ZumraPosts::class)->delete($this->dehors, $duMembre))->toThrow(AuthorizationException::class);

    app(ZumraPosts::class)->delete($this->membre, $duMembre);
    expect(ZumraPost::find($duMembre->id))->toBeNull();

    monde($this->yao, $this->zumra, 'discussion')->call('deletePost', $duResp->id)->assertDontSeeText('Message du responsable');
    expect(ZumraPost::count())->toBe(0);
});

it('ne laisse pas supprimer le message d’une autre ZUMRA depuis la Discussion de celle-ci', function () {
    $autre = zumra($this->president, 'Autre ZUMRA');
    $post = app(ZumraPosts::class)->post($this->president, $autre, 'Message ailleurs');

    monde($this->yao, $this->zumra, 'discussion')->call('deletePost', $post->id)->assertNotFound();
    monde($this->yao, $this->zumra, 'discussion')->call('deletePost', 'pas-un-uuid')->assertNotFound();
    expect(ZumraPost::count())->toBe(1);
});

it('perd l’accès à la Discussion en quittant la ZUMRA, et supprime les messages avec le compte', function () {
    app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Avant de partir');
    app(Memberships::class)->leave($this->membre, $this->zumra);

    expect(fn () => app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Après'))->toThrow(AuthorizationException::class);
    $this->actingAs($this->membre)->get(route('zumras.show', $this->zumra).'?onglet=discussion')->assertNotFound();

    fermerCompte($this->membre);
    expect(ZumraPost::count())->toBe(0);
});

// ─── Formation ───────────────────────────────────────────────────────────────

it('ouvre le Monde sur la Formation : savoirs partagés, ce que les membres savent faire et veulent apprendre', function () {
    publication($this->yao, PublicationType::Knowledge);
    app(Publications::class)->publish($this->yao, PublicationType::Knowledge, ['body' => 'Atelier de coupe'], $this->zumra);
    app(Publications::class)->publish($this->yao, PublicationType::Need, ['body' => 'Besoin hors Formation'], $this->zumra);

    Livewire::actingAs($this->dehors)->test(MondeZumra::class, ['zumra' => $this->zumra])
        ->assertSet('tab', 'formation')->assertSeeText('APPRENDRE ENSEMBLE')->assertSeeText('Atelier de coupe')
        ->assertDontSeeText('Besoin hors Formation')->assertSeeText('Tissage')->assertSeeText('Broderie')->assertSeeText('Comptabilité');
});

it('respecte la visibilité des profils dans les capacités de la Formation', function () {
    $discret = person('Membre Discret', ['Soudure'], ['Plomberie'], null, 'members');
    ajouteMembre($this->zumra, $discret);

    // Une personne inscrite voit un profil « inscrits seulement » ; un visiteur sans compte ne le voit pas.
    Livewire::actingAs($this->dehors)->test(MondeZumra::class, ['zumra' => $this->zumra])->assertSeeText('Soudure')->assertSeeText('Plomberie')->assertSeeText('Tissage');
    app('auth')->forgetGuards();
    Livewire::test(MondeZumra::class, ['zumra' => $this->zumra])->assertDontSeeText('Soudure')->assertSeeText('Tissage');
});

it('affiche un état vide honnête dans la Formation et le Fil', function () {
    $vide = zumra($this->dehors, 'ZUMRA sans publication');

    monde($this->dehors, $vide, 'formation')->assertSeeText('Aucun savoir partagé pour l’instant.');
    monde($this->dehors, $vide, 'fil')->assertSeeText('Rien dans le Fil de cette ZUMRA pour l’instant.');
});

it('pagine le Fil de la ZUMRA sans en montrer plus que ce qui est demandé', function () {
    for ($i = 1; $i <= 12; $i++) {
        app(Publications::class)->publish($this->yao, PublicationType::Need, ['body' => "Besoin numéro $i"], $this->zumra);
    }

    $c = monde($this->dehors, $this->zumra, 'fil');
    expect(substr_count($c->html(), 'Besoin numéro'))->toBeLessThan(12);
    $c->call('more')->assertSeeText('Besoin numéro 1');
});

// ─── Reconnaissance ──────────────────────────────────────────────────────────

it('laisse le seul super administrateur reconnaître une ZUMRA, avec journal et information du fondateur et des responsables', function () {
    foreach ([$this->yao, $this->resp, $this->membre, $this->dehors, User::factory()->moderator()->create()] as $autre) {
        expect(fn () => app(Zumras::class)->recognize($autre, $this->zumra))->toThrow(AuthorizationException::class);
    }
    expect($this->zumra->fresh()->isRecognized())->toBeFalse();

    monde($this->president, $this->zumra, 'formation')->assertSeeText('Reconnaître cette ZUMRA')->call('recognize')->assertHasNoErrors()->assertSeeText('Reconnue par GAMAD');

    $z = $this->zumra->fresh();
    expect($z->isRecognized())->toBeTrue()->and($z->recognized_by)->toBe($this->president->id);

    $entree = AdminAction::query()->where('action', 'zumra.recognized')->sole();
    expect($entree->actor_id)->toBe($this->president->id)->and($entree->subject_id)->toBe($this->zumra->id);

    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        expect(UserNotification::query()->where('user_id', $gestionnaire->id)->where('kind', NotificationKind::ZumraRecognized)->count())->toBe(1);
    }
    expect(UserNotification::query()->whereIn('user_id', [$this->membre->id, $this->dehors->id, $this->president->id])->count())->toBe(0);
});

it('ne reconnaît pas deux fois, et affiche le badge à tout le monde, visiteur compris', function () {
    app(Zumras::class)->recognize($this->president, $this->zumra);

    expect(fn () => app(Zumras::class)->recognize($this->president, $this->zumra))->toThrow(ValidationException::class, 'déjà reconnue');
    expect(AdminAction::where('action', 'zumra.recognized')->count())->toBe(1);

    Livewire::actingAs($this->dehors)->test(MondeZumra::class, ['zumra' => $this->zumra])->assertSeeText('Reconnue par GAMAD')->assertDontSeeText('Reconnaître cette ZUMRA');
    app('auth')->forgetGuards();
    Livewire::test(MondeZumra::class, ['zumra' => $this->zumra])->assertSeeText('Reconnue par GAMAD');
});

it('cache la carte de reconnaissance et refuse les actes aux personnes qui ne sont pas super administrateur', function () {
    foreach ([$this->yao, $this->resp, $this->membre] as $autre) {
        monde($autre, $this->zumra, 'formation')->assertDontSeeText('Reconnaître cette ZUMRA')->assertDontSeeText('Reconnaissance par GAMAD');
        monde($autre, $this->zumra, 'formation')->call('recognize')->assertForbidden();
        monde($autre, $this->zumra, 'formation')->set('recognitionReason', 'Motif')->call('withdrawRecognition')->assertForbidden();
    }
    expect($this->zumra->fresh()->isRecognized())->toBeFalse();
});

it('retire la reconnaissance avec un motif obligatoire de 500 caractères au plus, tracé au journal', function () {
    app(Zumras::class)->recognize($this->president, $this->zumra);

    foreach ([null, '   ', str_repeat('a', 501)] as $mauvais) {
        expect(fn () => app(Zumras::class)->withdrawRecognition($this->president, $this->zumra, $mauvais))->toThrow(ValidationException::class);
    }
    expect($this->zumra->fresh()->isRecognized())->toBeTrue();

    monde($this->president, $this->zumra, 'formation')->set('recognitionReason', 'Activité interrompue depuis un an.')->call('withdrawRecognition')->assertHasNoErrors()
        ->assertSeeText('Reconnaissance retirée.')->assertSeeText('Reconnaître cette ZUMRA');

    $z = $this->zumra->fresh();
    expect($z->isRecognized())->toBeFalse()->and($z->recognized_by)->toBeNull();

    $entree = AdminAction::query()->where('action', 'zumra.recognition_withdrawn')->sole();
    expect($entree->reason)->toBe('Activité interrompue depuis un an.')->and($entree->actor_id)->toBe($this->president->id);

    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        expect(UserNotification::query()->where('user_id', $gestionnaire->id)->where('kind', NotificationKind::ZumraRecognitionWithdrawn)->count())->toBe(1);
    }

    // Retirer ce qui n'existe plus est refusé, et la ZUMRA peut être reconnue de nouveau.
    expect(fn () => app(Zumras::class)->withdrawRecognition($this->president, $this->zumra, 'Encore'))->toThrow(ValidationException::class, 'n’est pas reconnue');
    app(Zumras::class)->recognize($this->president, $this->zumra);
    expect($this->zumra->fresh()->isRecognized())->toBeTrue();
});

it('filtre le Carrefour ZUMRA sur « Reconnues par GAMAD »', function () {
    $autre = zumra($this->dehors, 'ZUMRA non reconnue');
    app(Zumras::class)->recognize($this->president, $this->zumra);

    Livewire::actingAs($this->membre)->test(CarrefourZumras::class)->assertSeeText('Couture solidaire')->assertSeeText('ZUMRA non reconnue')->assertSeeText('Reconnue par GAMAD')
        ->call('toggleRecognized')->assertSet('recognized', true)->assertSeeText('Couture solidaire')->assertDontSeeText('ZUMRA non reconnue')
        ->call('toggleRecognized')->assertSeeText('ZUMRA non reconnue');
});
