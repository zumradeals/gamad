<?php

use App\Enums\NotificationKind;
use App\Enums\PublicationType;
use App\Livewire\CarrefourZumras;
use App\Livewire\ModerationZumras;
use App\Livewire\MondeZumra;
use App\Livewire\SignalerZumra;
use App\Models\AdminAction;
use App\Models\Publication;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\ZumraReport;
use App\Services\Publications;
use App\Services\ZumraModeration;
use App\Services\ZumraPosts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** D-215 : signaler une ZUMRA ou un message de Discussion ; suspension et masquage par la modération. */
beforeEach(function () {
    c1ModuleZumra('public');
    $this->fondatrice = person('Salimata Ouattara');
    $this->membre = person('Ibrahim Traoré');
    $this->autre = person('Nadia Kouassi');
    $this->dehors = person('Yao Brou');
    $this->moderateur = User::factory()->moderator()->create();
    $this->zumra = zumra($this->fondatrice);
    admis($this->zumra, $this->membre);
    admis($this->zumra, $this->autre);
    $this->service = app(ZumraModeration::class);
});

it('laisse une personne vérifiée signaler une ZUMRA, une seule fois, mais pas son fondateur', function () {
    $this->service->reportZumra($this->dehors, $this->zumra, 'spam', 'Publicité répétée');
    expect(ZumraReport::query()->open()->count())->toBe(1);

    expect(fn () => $this->service->reportZumra($this->dehors, $this->zumra, 'spam'))->toThrow(ValidationException::class);
    expect(fn () => $this->service->reportZumra($this->fondatrice, $this->zumra, 'spam'))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->reportZumra($this->membre, $this->zumra, 'inconnue'))->toThrow(ValidationException::class);
    expect(fn () => $this->service->reportZumra(User::factory()->unverified()->create(), $this->zumra, 'spam'))->toThrow(AuthorizationException::class);
});

it('propose le signalement par un écran, sans montrer le signalant', function () {
    Livewire::actingAs($this->dehors)->test(SignalerZumra::class, ['zumra' => $this->zumra])
        ->set('category', 'scam')->call('send')->assertHasNoErrors();

    $report = ZumraReport::query()->sole();
    expect($report->reporter_id)->toBe($this->dehors->id);
    Livewire::actingAs($this->moderateur)->test(ModerationZumras::class)->assertSee('Couture solidaire')->assertDontSee('Yao Brou');
});

it('laisse un membre signaler un message de la Discussion, jamais le sien, jamais hors de la ZUMRA', function () {
    $post = app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Un message déplacé.');

    $this->service->reportPost($this->autre, $post, 'offensive');
    expect(ZumraReport::query()->where('zumra_post_id', $post->id)->count())->toBe(1);

    expect(fn () => $this->service->reportPost($this->membre, $post, 'offensive'))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->reportPost($this->dehors, $post, 'offensive'))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->reportPost($this->autre, $post, 'offensive'))->toThrow(ValidationException::class);
    $this->actingAs($this->dehors)->get(route('zumras.discussion.report', [$this->zumra, $post]))->assertNotFound();
    $this->actingAs($this->fondatrice)->get(route('zumras.discussion.report', [$this->zumra, $post]))->assertOk()->assertSee('Un modérateur lira ce message');
});

it('suspend une ZUMRA avec motif : retirée du Carrefour, introuvable pour le public, visible des membres, journalisée, responsables prévenus', function () {
    $this->service->reportZumra($this->dehors, $this->zumra, 'scam');

    expect(fn () => $this->service->suspend($this->membre, $this->zumra, 'Non.'))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->suspend($this->moderateur, $this->zumra, ''))->toThrow(ValidationException::class);

    $this->service->suspend($this->moderateur, $this->zumra, 'Arnaque avérée.');
    expect($this->zumra->fresh()->isSuspended())->toBeTrue()
        ->and(ZumraReport::query()->open()->count())->toBe(0)
        ->and(AdminAction::query()->where('action', 'zumra.suspended')->where('reason', 'Arnaque avérée.')->exists())->toBeTrue()
        ->and(UserNotification::query()->where('user_id', $this->fondatrice->id)->where('kind', NotificationKind::ZumraSuspended->value)->exists())->toBeTrue();

    Livewire::actingAs($this->dehors)->test(CarrefourZumras::class)->assertDontSee($this->zumra->name);
    $this->actingAs($this->dehors)->get(route('zumras.show', $this->zumra))->assertNotFound();
    $this->get(route('zumras.show', $this->zumra))->assertNotFound();
    $this->actingAs($this->membre)->get(route('zumras.show', $this->zumra))->assertOk()->assertSee('suspendue par la modération')->assertSee('Arnaque avérée.');
    $this->actingAs($this->moderateur)->get(route('zumras.show', $this->zumra))->assertOk();
});

it('ferme les actions d’une ZUMRA suspendue : adhésion, invitation, publication, Discussion', function () {
    $publication = app(Publications::class)->publish($this->fondatrice, PublicationType::Knowledge, ['body' => 'Un savoir de la ZUMRA.'], $this->zumra);
    $this->service->suspend($this->moderateur, $this->zumra, 'Examen en cours.');

    expect(fn () => app(Publications::class)->publish($this->fondatrice, PublicationType::Knowledge, ['body' => 'Encore.'], $this->zumra))->toThrow(AuthorizationException::class);
    expect(fn () => app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Bonjour'))->toThrow(AuthorizationException::class);
    $this->actingAs($this->fondatrice)->get(route('zumras.invite', $this->zumra))->assertForbidden();
    $this->actingAs($this->dehors)->get(route('zumras.show', $this->zumra))->assertNotFound();

    // Les publications de la ZUMRA disparaissent des fils, sauf pour ses membres et la modération.
    expect(Publication::query()->visibleTo($this->dehors)->whereKey($publication->id)->exists())->toBeFalse()
        ->and(Publication::query()->visibleTo($this->membre)->whereKey($publication->id)->exists())->toBeTrue()
        ->and(Publication::query()->visibleTo($this->moderateur)->whereKey($publication->id)->exists())->toBeTrue();
});

it('rétablit une ZUMRA suspendue : tout redevient comme avant', function () {
    $this->service->suspend($this->moderateur, $this->zumra, 'Examen.');
    $this->service->reinstate($this->moderateur, $this->zumra, 'Vérifiée, tout est en ordre.');

    expect($this->zumra->fresh()->isSuspended())->toBeFalse()
        ->and(AdminAction::query()->where('action', 'zumra.reinstated')->exists())->toBeTrue();
    $this->actingAs($this->dehors)->get(route('zumras.show', $this->zumra))->assertOk();
    app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Bonjour');
});

it('masque un message signalé : remplacé par une mention, auteur prévenu, journalisé, rétablissable', function () {
    $post = app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Un propos injurieux.');
    $this->service->reportPost($this->autre, $post, 'offensive');

    expect(fn () => $this->service->hidePost($this->membre, $post, 'Non.'))->toThrow(AuthorizationException::class);
    $this->service->hidePost($this->moderateur, $post, 'Propos injurieux.');

    expect($post->fresh()->hidden_at)->not->toBeNull()
        ->and(ZumraReport::query()->open()->count())->toBe(0)
        ->and(UserNotification::query()->where('user_id', $this->membre->id)->where('kind', NotificationKind::ZumraMessageHidden->value)->exists())->toBeTrue()
        ->and(AdminAction::query()->where('action', 'zumra.post.hidden')->exists())->toBeTrue();

    monde($this->autre, $this->zumra, 'discussion')->assertSee('Message masqué par la modération.')->assertDontSee('Un propos injurieux.');

    $this->service->restorePost($this->moderateur, $post, 'Contexte clarifié.');
    monde($this->autre, $this->zumra, 'discussion')->assertSee('Un propos injurieux.');
});

it('classe un signalement sans suite sans rien masquer', function () {
    $post = app(ZumraPosts::class)->post($this->membre, $this->zumra, 'Un message correct.');
    $this->service->reportPost($this->autre, $post, 'other');
    $this->service->dismissPost($this->moderateur, $post, 'Rien d’anormal.');

    expect($post->fresh()->hidden_at)->toBeNull()->and(ZumraReport::query()->open()->count())->toBe(0);

    $this->service->reportZumra($this->dehors, $this->zumra, 'spam');
    $this->service->dismissZumra($this->moderateur, $this->zumra, 'Rien d’anormal.');
    expect($this->zumra->fresh()->isSuspended())->toBeFalse()->and(ZumraReport::query()->open()->count())->toBe(0);
});

it('réserve la file de modération des ZUMRA à la modération, et la ferme avec le module', function () {
    $this->actingAs($this->membre)->get(route('moderation.zumras'))->assertForbidden();
    $this->actingAs($this->moderateur)->get(route('moderation.zumras'))->assertOk();

    c1ModuleZumra('off');
    $this->actingAs($this->moderateur)->get(route('moderation.zumras'))->assertNotFound();
    $this->actingAs($this->dehors)->get(route('zumras.report', $this->zumra))->assertNotFound();
});

it('montre la file de modération avec ses décisions depuis l’écran', function () {
    $this->service->reportZumra($this->dehors, $this->zumra, 'scam', 'Demande d’argent');
    Livewire::actingAs($this->moderateur)->test(ModerationZumras::class)
        ->assertSee('Demande d’argent')->set('reasons.'.$this->zumra->id, 'Arnaque.')->call('suspend', $this->zumra->id)
        ->assertSee('Aucun signalement à examiner.')->call('setTab', 'suspendues')->assertSee('Arnaque.')
        ->call('reinstate', $this->zumra->id)->assertSee('Aucune ZUMRA suspendue');
});

it('propose les liens de signalement et la modération sur la page de la ZUMRA', function () {
    Livewire::actingAs($this->dehors)->test(MondeZumra::class, ['zumra' => $this->zumra])->assertSee('Signaler cette ZUMRA')->assertDontSee('Suspendre la ZUMRA');
    Livewire::actingAs($this->fondatrice)->test(MondeZumra::class, ['zumra' => $this->zumra])->assertDontSee('Signaler cette ZUMRA');
    Livewire::actingAs($this->moderateur)->test(MondeZumra::class, ['zumra' => $this->zumra])->assertSee('Suspendre la ZUMRA')
        ->set('moderationReason', 'Motif de test.')->call('suspend');
    expect($this->zumra->fresh()->isSuspended())->toBeTrue();
});
