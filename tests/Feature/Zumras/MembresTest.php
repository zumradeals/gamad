<?php

use App\Enums\NotificationKind;
use App\Enums\ZumraRole;
use App\Livewire\Espace;
use App\Livewire\MondeZumra;
use App\Livewire\PageNotifications;
use App\Models\AdminAction;
use App\Models\Invitation;
use App\Models\JoinRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Services\JoinRequests;
use App\Services\Memberships;
use App\Services\Moderation;
use App\Services\Notifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->yao = person('Yao Kouassi', ['Couture']);
    $this->zumra = zumra($this->yao, 'Couture solidaire');
    $this->resp = person('Responsable Un', []);
    $this->resp2 = person('Responsable Deux', []);
    $this->awa = person('Awa Traoré', []);
    $this->moussa = person('Moussa Koné', []);
    ajouteMembre($this->zumra, $this->resp, ZumraRole::Manager);
    ajouteMembre($this->zumra, $this->resp2, ZumraRole::Manager);
    ajouteMembre($this->zumra, $this->awa);
    ajouteMembre($this->zumra, $this->moussa);
});

function lignes(): Memberships
{
    return app(Memberships::class);
}

// ─── Quitter ─────────────────────────────────────────────────────────────────

it('laisse un membre ou un responsable quitter la ZUMRA, puis redemander à la rejoindre', function () {
    Livewire::actingAs($this->awa)->test(MondeZumra::class, ['zumra' => $this->zumra])->set('tab', 'membres')->assertSeeText('Quitter la ZUMRA')->call('leave')->assertHasNoErrors();
    lignes()->leave($this->resp, $this->zumra);

    expect($this->zumra->roleOf($this->awa))->toBeNull()->and($this->zumra->roleOf($this->resp))->toBeNull();
    monde($this->awa, $this->zumra)->assertSeeText('Demander à rejoindre');
});

it('ne laisse pas le fondateur quitter sa ZUMRA, et ne propose pas le bouton', function () {
    expect(fn () => lignes()->leave($this->yao, $this->zumra))->toThrow(AuthorizationException::class);

    monde($this->yao, $this->zumra)->assertDontSeeText('Quitter la ZUMRA');
    expect($this->zumra->roleOf($this->yao))->toBe(ZumraRole::Founder);
});

it('ne propose pas « Quitter » à qui n’est pas dans la ZUMRA', function () {
    $dehors = person('Dehors Total', []);

    monde($dehors, $this->zumra)->assertDontSeeText('Quitter la ZUMRA');
    expect(fn () => lignes()->leave($dehors, $this->zumra))->toThrow(AuthorizationException::class);
});

// ─── Retirer ─────────────────────────────────────────────────────────────────

it('laisse un responsable retirer un simple membre, qui en est informé', function () {
    monde($this->resp, $this->zumra)->call('removeMember', ligne($this->zumra, $this->awa)->id)->assertHasNoErrors();

    expect($this->zumra->roleOf($this->awa))->toBeNull();
    $n = UserNotification::query()->where('user_id', $this->awa->id)->sole();
    expect($n->kind)->toBe(NotificationKind::ZumraMemberRemoved)->and($n->actor_id)->toBe($this->resp->id)->and($n->zumra_id)->toBe($this->zumra->id);
});

it('laisse le fondateur retirer un responsable ou un membre, pas un responsable retirer un responsable', function () {
    expect(fn () => lignes()->remove($this->resp, $this->zumra, ligne($this->zumra, $this->resp2)))->toThrow(AuthorizationException::class);
    expect($this->zumra->roleOf($this->resp2))->toBe(ZumraRole::Manager);

    lignes()->remove($this->yao, $this->zumra, ligne($this->zumra, $this->resp2));
    lignes()->remove($this->yao, $this->zumra, ligne($this->zumra, $this->moussa));

    expect($this->zumra->roleOf($this->resp2))->toBeNull()->and($this->zumra->roleOf($this->moussa))->toBeNull();
});

it('ne retire jamais le fondateur, ni soi-même', function () {
    expect(fn () => lignes()->remove($this->resp, $this->zumra, ligne($this->zumra, $this->yao)))->toThrow(AuthorizationException::class)
        ->and(fn () => lignes()->remove($this->yao, $this->zumra, ligne($this->zumra, $this->yao)))->toThrow(AuthorizationException::class)
        ->and(fn () => lignes()->remove($this->awa, $this->zumra, ligne($this->zumra, $this->awa)))->toThrow(AuthorizationException::class);

    expect($this->zumra->roleOf($this->yao))->toBe(ZumraRole::Founder);
});

it('refuse à un simple membre ou à une personne extérieure de retirer qui que ce soit', function () {
    foreach ([$this->awa, person('Dehors Total', [])] as $qui) {
        expect(fn () => lignes()->remove($qui, $this->zumra, ligne($this->zumra, $this->moussa)))->toThrow(AuthorizationException::class);
    }
    expect($this->zumra->roleOf($this->moussa))->toBe(ZumraRole::Member);
});

it('ne retire pas un membre d’une autre ZUMRA en changeant l’identifiant', function () {
    $autre = zumra(User::factory()->create(), 'Autre ZUMRA');
    $etranger = person('Étranger Total', []);
    ajouteMembre($autre, $etranger);

    expect(fn () => monde($this->yao, $this->zumra)->call('removeMember', ligne($autre, $etranger)->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => lignes()->remove($this->yao, $this->zumra, ligne($autre, $etranger)))->toThrow(AuthorizationException::class);
    expect($autre->roleOf($etranger))->toBe(ZumraRole::Member);
});

// ─── Nommer et retirer un responsable ────────────────────────────────────────

it('laisse le fondateur nommer un responsable, qui en est informé, et lui retirer ce rôle', function () {
    monde($this->yao, $this->zumra)->call('promote', ligne($this->zumra, $this->awa)->id)->assertHasNoErrors();

    expect($this->zumra->roleOf($this->awa))->toBe(ZumraRole::Manager);
    expect(UserNotification::query()->where('user_id', $this->awa->id)->sole()->kind)->toBe(NotificationKind::ZumraRoleGranted);
    monde($this->awa, $this->zumra)->assertSeeText('Modifier la ZUMRA');

    monde($this->yao, $this->zumra)->call('demote', ligne($this->zumra, $this->awa)->id);
    expect($this->zumra->roleOf($this->awa))->toBe(ZumraRole::Member);
    monde($this->awa, $this->zumra)->assertDontSeeText('Modifier la ZUMRA');
});

it('réserve le choix des responsables au fondateur', function () {
    foreach ([$this->resp, $this->awa] as $qui) {
        expect(fn () => lignes()->promote($qui, $this->zumra, ligne($this->zumra, $this->moussa)))->toThrow(AuthorizationException::class)
            ->and(fn () => lignes()->demote($qui, $this->zumra, ligne($this->zumra, $this->resp2)))->toThrow(AuthorizationException::class);
    }

    expect($this->zumra->roleOf($this->moussa))->toBe(ZumraRole::Member)->and($this->zumra->roleOf($this->resp2))->toBe(ZumraRole::Manager);
});

it('ne touche pas au rôle du fondateur, et nommer deux fois ne prévient qu’une fois', function () {
    expect(fn () => lignes()->promote($this->yao, $this->zumra, ligne($this->zumra, $this->yao)))->toThrow(AuthorizationException::class)
        ->and(fn () => lignes()->demote($this->yao, $this->zumra, ligne($this->zumra, $this->yao)))->toThrow(AuthorizationException::class);

    lignes()->promote($this->yao, $this->zumra, ligne($this->zumra, $this->awa));
    lignes()->promote($this->yao, $this->zumra, ligne($this->zumra, $this->awa));

    expect(UserNotification::query()->where('user_id', $this->awa->id)->count())->toBe(1);
});

it('montre les actions de gestion selon le rôle de la personne qui regarde', function () {
    monde($this->yao, $this->zumra)->assertSeeText('Nommer responsable')->assertSeeText('Retirer le rôle de responsable')->assertSeeText('Retirer de la ZUMRA');

    $html = monde($this->resp, $this->zumra)->html();
    expect($html)->toContain('Retirer de la ZUMRA')->not->toContain('Nommer responsable')->not->toContain('Retirer le rôle de responsable');

    $html = monde($this->awa, $this->zumra)->html();
    expect($html)->not->toContain('Retirer de la ZUMRA')->not->toContain('Nommer responsable');
});

// ─── La fondation passe à un autre quand le fondateur supprime son compte (D-115) ───

it('passe la fondation au responsable le plus ancien, informe la personne et inscrit l’acte au journal', function () {
    ZumraMembership::query()->where('user_id', $this->resp->id)->update(['joined_at' => now()->subDays(10)]);
    ZumraMembership::query()->where('user_id', $this->resp2->id)->update(['joined_at' => now()->subDays(5)]);
    ZumraMembership::query()->where('user_id', $this->awa->id)->update(['joined_at' => now()->subDays(20)]);

    fermerCompte($this->yao);

    expect(Zumra::query()->whereKey($this->zumra->id)->exists())->toBeTrue()
        ->and($this->zumra->roleOf($this->resp))->toBe(ZumraRole::Founder)->and($this->zumra->roleOf($this->resp2))->toBe(ZumraRole::Manager)
        ->and($this->zumra->memberships()->where('role', 'founder')->count())->toBe(1);

    $n = UserNotification::query()->where('user_id', $this->resp->id)->where('kind', NotificationKind::ZumraFounderTransferred)->sole();
    expect($n->actor_id)->toBeNull()->and($n->zumra_id)->toBe($this->zumra->id);

    $a = AdminAction::query()->where('action', 'zumra.founder_transferred')->sole();
    expect($a->subject_id)->toBe($this->zumra->id)->and($a->payload['to'])->toBe($this->resp->id)->and($a->payload['from'])->toBe($this->yao->id);
});

it('passe la fondation au membre le plus ancien quand il n’y a pas de responsable', function () {
    $seule = zumra($this->awa, 'Petite ZUMRA');
    $premier = person('Premier Arrivé', []);
    $second = person('Second Arrivé', []);
    ajouteMembre($seule, $second);
    ajouteMembre($seule, $premier);
    ZumraMembership::query()->where('user_id', $premier->id)->update(['joined_at' => now()->subDays(9)]);
    ZumraMembership::query()->where('user_id', $second->id)->update(['joined_at' => now()->subDays(2)]);

    fermerCompte($this->awa);

    expect($seule->roleOf($premier))->toBe(ZumraRole::Founder)->and($seule->roleOf($second))->toBe(ZumraRole::Member);
});

it('supprime la ZUMRA quand le fondateur est seul, sans rien inscrire au journal', function () {
    $seule = zumra($this->awa, 'Petite ZUMRA');

    fermerCompte($this->awa);

    expect(Zumra::query()->whereKey($seule->id)->exists())->toBeFalse()->and(AdminAction::query()->where('action', 'zumra.founder_transferred')->count())->toBe(0);
});

it('traite chaque ZUMRA du fondateur qui supprime son compte', function () {
    $deuxieme = zumra($this->yao, 'Seconde ZUMRA');
    ajouteMembre($deuxieme, $this->moussa);
    $troisieme = zumra($this->yao, 'Troisième ZUMRA');

    fermerCompte($this->yao);

    expect($this->zumra->roleOf($this->resp))->toBe(ZumraRole::Founder)->and($deuxieme->roleOf($this->moussa))->toBe(ZumraRole::Founder)
        ->and(Zumra::query()->whereKey($troisieme->id)->exists())->toBeFalse();
});

it('ne change rien quand c’est un simple membre qui supprime son compte, et efface ses demandes et invitations', function () {
    app(JoinRequests::class)->propose($this->awa->fresh(), zumra(User::factory()->create(), 'Autre ZUMRA'));
    fermerCompte($this->moussa);

    expect($this->zumra->roleOf($this->yao))->toBe(ZumraRole::Founder)->and($this->zumra->memberships()->count())->toBe(4);
    fermerCompte($this->awa);
    expect(JoinRequest::count())->toBe(0)->and(Invitation::count())->toBe(0);
});

it('supprime le compte par l’écran Espace avec la même règle', function () {
    Livewire::actingAs($this->yao)->test(Espace::class)->set('password', 'password')->call('deleteAccount');

    expect(User::query()->whereKey($this->yao->id)->exists())->toBeFalse()->and(Zumra::query()->whereKey($this->zumra->id)->exists())->toBeTrue()
        ->and($this->zumra->memberships()->where('role', 'founder')->count())->toBe(1);
});

it('annonce le transfert de la fondation dans les notifications, sans nom de personne', function () {
    fermerCompte($this->yao);

    Livewire::actingAs($this->resp)->test(PageNotifications::class)
        ->assertSeeText('La fondation de « Couture solidaire » vous a été confiée')->assertDontSeeText('Personne supprimée');
});

// ─── Notifications et ouverture ──────────────────────────────────────────────

it('ouvre chaque notification d’une ZUMRA sur son Monde, et revient à la liste si la ZUMRA a disparu', function () {
    foreach ([NotificationKind::ZumraRequestReceived, NotificationKind::ZumraRequestAccepted, NotificationKind::ZumraInvitationReceived, NotificationKind::ZumraInvitationAccepted, NotificationKind::ZumraRoleGranted, NotificationKind::ZumraMemberRemoved, NotificationKind::ZumraFounderTransferred] as $genre) {
        app(Notifier::class)->push($this->awa, $genre, $this->yao, zumra: $this->zumra);
    }

    foreach (UserNotification::query()->where('user_id', $this->awa->id)->get() as $n) {
        $this->actingAs($this->awa)->get(route('notifications.open', $n))->assertRedirect(route('zumras.show', $this->zumra));
    }

    $this->zumra->delete();
    expect(UserNotification::query()->where('user_id', $this->awa->id)->count())->toBe(0);
});

it('ouvre les notifications de publication masquée ou rétablie sur la publication (omission de la PR 2c)', function () {
    $mod = User::factory()->moderator()->create();
    $p = publication($this->awa, data: ['body' => 'Une publication à modérer']);
    app(Moderation::class)->hide($mod, $p, 'Motif');
    app(Moderation::class)->restore($mod, $p->fresh());

    $notifications = UserNotification::query()->where('user_id', $this->awa->id)->get();
    expect($notifications)->toHaveCount(2);

    foreach ($notifications as $n) {
        $this->actingAs($this->awa)->get(route('notifications.open', $n))->assertRedirect(route('publications.show', $p));
    }
});

it('affiche les phrases des notifications de ZUMRA, avec le nom de la ZUMRA', function () {
    app(JoinRequests::class)->propose($this->awa->fresh(), $z = zumra(User::factory()->create(['name' => 'Fondatrice Autre']), 'Autre ZUMRA'));

    Livewire::actingAs(User::query()->where('name', 'Fondatrice Autre')->first())->test(PageNotifications::class)
        ->assertSeeText('Awa Traoré')->assertSeeText('demande à rejoindre « Autre ZUMRA »');
    expect(Carbon::now())->not->toBeNull();
});
