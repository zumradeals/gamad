<?php

use App\Enums\NotificationKind;
use App\Models\User;
use App\Models\UserNotification;

/** R1 : défauts de parcours trouvés pendant la recette globale, avec leur garde-fou. */
it('n’imbrique jamais deux zones principales dans une page : une seule balise main par page', function () {
    $membre = User::factory()->create();

    foreach (['personnes.index', 'entreprises.index', 'opportunites.index'] as $route) {
        $this->get(route($route))->assertOk();
        expect(substr_count(strtolower($this->get(route($route))->getContent()), '<main'))->toBe(1, "$route doit avoir une seule zone principale (visiteur)");
        expect(substr_count(strtolower($this->actingAs($membre)->get(route($route))->getContent()), '<main'))->toBe(1, "$route doit avoir une seule zone principale (membre)");
        auth()->logout();
    }
});

it('mène la notification d’une demande de relation à la rubrique où l’on peut y répondre', function () {
    $moi = User::factory()->create();
    $autre = User::factory()->create();
    $alerte = new UserNotification(['kind' => NotificationKind::ConnectionRequested]);
    $alerte->user()->associate($moi);
    $alerte->actor()->associate($autre);
    $alerte->save();

    $this->actingAs($moi)->get(route('notifications.open', $alerte))->assertRedirect(route('espace', ['rubrique' => 'relations']).'#mes-liens');
    $this->get(route('espace', ['rubrique' => 'relations']))->assertOk()->assertSee('id="mes-liens"', false);
});

it('mène la notification de pistes à la rubrique qui les affiche', function () {
    $moi = User::factory()->create();
    $alerte = new UserNotification(['kind' => NotificationKind::MatchFound]);
    $alerte->user()->associate($moi);
    $alerte->save();

    $this->actingAs($moi)->get(route('notifications.open', $alerte))->assertRedirect(route('espace', ['rubrique' => 'pistes']).'#suggestions');
    $this->get(route('espace', ['rubrique' => 'pistes']))->assertOk()->assertSee('id="suggestions"', false);
});

it('laisse une personne dont l’e-mail n’est pas encore vérifié continuer à découvrir GAMAD', function () {
    $this->actingAs(User::factory()->unverified()->create())->get(route('verification.notice'))->assertOk()
        ->assertSeeText('Rien ne presse')->assertSee('href="'.route('personnes.index').'"', false);
});

it('ne culpabilise ni ne presse un membre qui revient sans rien entreprendre', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('fil'))->assertOk()->getContent();

    foreach (['N’oubliez pas', 'Ne manquez pas', 'Dépêchez', 'Chaque contribution compte', 'Vous n’avez encore rien'] as $pression) {
        expect($html)->not->toContain($pression);
    }
    $this->get(route('personnes.index'))->assertOk();
    $this->get(route('entreprises.index'))->assertOk();
});
