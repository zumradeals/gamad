<?php

use App\Enums\NotificationKind;
use App\Enums\ZumraRole;
use App\Livewire\CarrefourZumras;
use App\Livewire\FormulaireZumra;
use App\Livewire\MondeZumra;
use App\Models\AdminAction;
use App\Models\Domain;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Zumra;
use App\Services\JoinRequests;
use App\Services\Memberships;
use App\Services\ZumraModeration;
use App\Services\Zumras;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** D-216 : adhésion libre par défaut (ou sur approbation), fondateur qui confie la fondation et quitte, fermeture d'une ZUMRA seule. */
beforeEach(function () {
    c1ModuleZumra('public');
    $this->fondatrice = person('Salimata Ouattara');
    $this->ibrahim = person('Ibrahim Traoré');
    $this->nadia = person('Nadia Kouassi');
});

function zumraLibre($test, array $data = []): Zumra
{
    return zumra($test->fondatrice, 'Atelier libre', $data + ['join_mode' => 'open']);
}

it('crée une ZUMRA en adhésion libre par défaut, le responsable pouvant choisir la validation', function () {
    Livewire::actingAs($this->fondatrice)->test(FormulaireZumra::class)
        ->set('name', 'Groupe ouvert')->set('sphere', 'social')->set('domain_id', Domain::query()->first()->id)->set('format', 'online')->set('mission', 'Un groupe ouvert à toutes et tous.')
        ->call('save')->assertHasNoErrors();
    expect(Zumra::query()->where('name', 'Groupe ouvert')->sole()->join_mode->value)->toBe('open');

    Livewire::actingAs($this->fondatrice)->test(FormulaireZumra::class)
        ->set('name', 'Groupe sur approbation')->set('sphere', 'social')->set('domain_id', Domain::query()->first()->id)->set('format', 'online')->set('mission', 'Un groupe qui examine.')->set('join_mode', 'approval')
        ->call('save')->assertHasNoErrors();
    expect(Zumra::query()->where('name', 'Groupe sur approbation')->sole()->join_mode->value)->toBe('approval');

    expect(fn () => app(Zumras::class)->create($this->fondatrice, ['name' => 'Autre groupe', 'domain_id' => Domain::query()->first()->id, 'format' => 'online', 'sphere' => 'social', 'mission' => 'Texte.', 'join_mode' => 'bidon']))
        ->toThrow(ValidationException::class);
});

it('fait rejoindre tout de suite une ZUMRA libre, prévient les responsables et refuse la demande', function () {
    $zumra = zumraLibre($this);

    Livewire::actingAs($this->ibrahim)->test(MondeZumra::class, ['zumra' => $zumra])->assertSee('Rejoindre la ZUMRA')->assertDontSee('Demander à rejoindre')->call('join');

    expect($zumra->roleOf($this->ibrahim))->toBe(ZumraRole::Member)
        ->and(UserNotification::query()->where('user_id', $this->fondatrice->id)->where('kind', NotificationKind::ZumraMemberJoined->value)->exists())->toBeTrue();
    expect(fn () => app(JoinRequests::class)->propose($this->nadia, $zumra))->toThrow(ValidationException::class);
    expect(fn () => app(JoinRequests::class)->join($this->ibrahim, $zumra))->toThrow(AuthorizationException::class);
    expect(fn () => app(JoinRequests::class)->join(User::factory()->unverified()->create(), $zumra))->toThrow(AuthorizationException::class);
});

it('garde la demande pour une ZUMRA sur approbation, et refuse l’entrée directe', function () {
    $zumra = zumraLibre($this, ['join_mode' => 'approval']);

    Livewire::actingAs($this->ibrahim)->test(MondeZumra::class, ['zumra' => $zumra])->assertSee('Demander à rejoindre')->assertDontSee('Rejoindre la ZUMRA');
    expect(fn () => app(JoinRequests::class)->join($this->ibrahim, $zumra))->toThrow(ValidationException::class)
        ->and($zumra->roleOf($this->ibrahim))->toBeNull();
    admis($zumra, $this->ibrahim);
    expect($zumra->fresh()->roleOf($this->ibrahim))->toBe(ZumraRole::Member);
});

it('laisse le responsable changer de mode, et rouvre l’entrée à une personne dont la demande avait été déclinée', function () {
    $zumra = zumraLibre($this, ['join_mode' => 'approval']);
    $demande = app(JoinRequests::class)->propose($this->ibrahim, $zumra);
    app(JoinRequests::class)->decline($this->fondatrice, $demande);
    expect(fn () => app(JoinRequests::class)->join($this->ibrahim, $zumra))->toThrow(AuthorizationException::class);

    app(Zumras::class)->update($this->fondatrice, $zumra, ['city_id' => null, 'format' => 'online', 'mission' => 'Mission.', 'join_mode' => 'open']);
    app(JoinRequests::class)->join($this->ibrahim, $zumra->fresh());
    expect($zumra->fresh()->roleOf($this->ibrahim))->toBe(ZumraRole::Member);

    $this->actingAs($this->nadia)->get(route('zumras.edit', $zumra))->assertNotFound();
});

it('conserve l’adhésion sur demande des ZUMRA déjà créées avant la règle de l’adhésion libre', function () {
    $zumra = zumra($this->fondatrice, 'Atelier ancien', ['join_mode' => 'approval']);
    DB::table('zumras')->where('id', $zumra->id)->update(['join_mode' => 'approval']);

    expect($zumra->fresh()->joinsFreely())->toBeFalse();
});

it('laisse le fondateur confier la fondation à un membre, puis quitter la ZUMRA', function () {
    $zumra = zumraLibre($this);
    admis($zumra, $this->ibrahim);
    $service = app(Memberships::class);
    $ligne = ligne($zumra, $this->ibrahim);

    expect(fn () => $service->transferFoundation($this->ibrahim, $zumra, ligne($zumra, $this->fondatrice)))->toThrow(AuthorizationException::class);
    expect(fn () => $service->leave($this->fondatrice, $zumra))->toThrow(AuthorizationException::class);

    $service->transferFoundation($this->fondatrice, $zumra, $ligne);

    expect($zumra->fresh()->roleOf($this->ibrahim))->toBe(ZumraRole::Founder)
        ->and($zumra->fresh()->roleOf($this->fondatrice))->toBe(ZumraRole::Manager)
        ->and(UserNotification::query()->where('user_id', $this->ibrahim->id)->where('kind', NotificationKind::ZumraFounderGiven->value)->exists())->toBeTrue()
        ->and(AdminAction::query()->where('action', 'zumra.founder_transferred')->exists())->toBeTrue();

    $service->leave($this->fondatrice, $zumra->fresh());
    expect($zumra->fresh()->roleOf($this->fondatrice))->toBeNull()->and($zumra->memberships()->count())->toBe(1);
});

it('propose au fondateur de confier la fondation depuis la page, ou de fermer sa ZUMRA s’il est seul', function () {
    $zumra = zumraLibre($this);

    monde($this->fondatrice, $zumra)->assertSee('Passer la main')->assertSee('Fermer la ZUMRA')->assertDontSee('Confier la fondation');
    admis($zumra, $this->ibrahim);
    $page = monde($this->fondatrice, $zumra)->assertSee('Confier la fondation')->assertDontSee('Fermer la ZUMRA');
    $page->call('transferFoundation', ligne($zumra, $this->ibrahim)->id);
    expect($zumra->fresh()->roleOf($this->ibrahim))->toBe(ZumraRole::Founder);

    monde($this->fondatrice, $zumra->fresh())->assertSee('Quitter la ZUMRA')->assertDontSee('Passer la main');
});

it('ferme une ZUMRA dont le fondateur est le seul membre : rien n’est supprimé, il peut la rouvrir', function () {
    $zumra = zumraLibre($this);
    admis($zumra, $this->ibrahim);
    $service = app(Memberships::class);

    expect(fn () => $service->close($this->fondatrice, $zumra))->toThrow(AuthorizationException::class);
    ligne($zumra, $this->ibrahim)->delete();

    expect(fn () => $service->close($this->ibrahim, $zumra))->toThrow(AuthorizationException::class);
    $service->close($this->fondatrice, $zumra);

    expect($zumra->fresh()->isClosed())->toBeTrue()->and(AdminAction::query()->where('action', 'zumra.closed')->exists())->toBeTrue();
    Livewire::actingAs($this->nadia)->test(CarrefourZumras::class)->assertDontSee('Atelier libre');
    $this->actingAs($this->nadia)->get(route('zumras.show', $zumra))->assertNotFound();
    $this->actingAs($this->fondatrice)->get(route('zumras.show', $zumra))->assertOk()->assertSee('Cette ZUMRA est fermée')->assertSee('Rouvrir la ZUMRA');
    expect(fn () => app(JoinRequests::class)->join($this->nadia, $zumra))->toThrow(AuthorizationException::class);

    $service->reopen($this->fondatrice, $zumra);
    expect($zumra->fresh()->isClosed())->toBeFalse();
    $this->actingAs($this->nadia)->get(route('zumras.show', $zumra))->assertOk();
});

it('ne laisse pas le fondateur rouvrir une ZUMRA que la modération a suspendue', function () {
    $zumra = zumraLibre($this);
    app(Memberships::class)->close($this->fondatrice, $zumra);
    app(ZumraModeration::class)->suspend(User::factory()->moderator()->create(), $zumra->fresh(), 'Motif.');

    expect(fn () => app(Memberships::class)->reopen($this->fondatrice, $zumra->fresh()))->toThrow(AuthorizationException::class);
});
