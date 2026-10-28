<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Core\Identity\IdentityEvent;
use App\Core\Identity\Sessions;
use App\Livewire\Espace;
use App\Livewire\MonAcces;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

// « Mon accès » : changer son mot de passe, voir et révoquer ses propres sessions. Le pilote « database » est celui de la production.

beforeEach(function () {
    config(['session.driver' => 'database']);
    $this->awa = person('Awa');
    $this->awa->forceFill(['password' => 'motdepasse1'])->save();
});

function ouvrirSession(User $user, string $id, ?int $activite = null, string $agent = 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0 Mobile Safari/537.36'): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '10.0.0.7', 'user_agent' => $agent, 'payload' => '', 'last_activity' => $activite ?? time()]);
}

function courante(): string
{
    return session()->getId();
}

/** Ouvre la page et enregistre la session courante de la personne (l'identifiant de session est fixé par le test Livewire). */
function monAcces(User $user)
{
    $page = Livewire::actingAs($user)->test(MonAcces::class);
    DB::table('sessions')->updateOrInsert(['id' => courante()], ['user_id' => $user->id, 'ip_address' => '10.0.0.7', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0', 'payload' => '', 'last_activity' => time()]);

    return $page->call('$refresh');
}

it('s’affiche dans Mon espace › Paramètres', function () {
    Livewire::actingAs($this->awa)->test(Espace::class)->call('show', 'compte')->assertSee('Mon accès')->assertSee('Appareils connectés');
});

it('change le mot de passe avec le mot de passe actuel, déconnecte les autres appareils et garde celui-ci', function () {
    ouvrirSession($this->awa, 'ailleurs');
    $autre = person('Yao');
    ouvrirSession($autre, 'autrui');

    $page = monAcces($this->awa);
    $page->set('current_password', 'motdepasse1')->set('password', 'nouveaumdp9')->set('password_confirmation', 'nouveaumdp9')
        ->call('changePassword')->assertHasNoErrors()->assertSee('1 autre(s) appareil(s)');

    expect(Hash::check('nouveaumdp9', $this->awa->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->pluck('id')->sort()->values()->all())->toBe(collect(['autrui', courante()])->sort()->values()->all());

    $event = IdentityEvent::query()->where('type', IdentityEvent::PASSWORD_CHANGED)->sole();
    expect($event->user_id)->toBe($this->awa->id)->and($event->context)->toBe(['sessions_revoked' => 1])
        ->and(json_encode($event->context))->not->toContain('nouveaumdp9')->not->toContain('10.0.0.7');
});

it('refuse un mauvais mot de passe actuel, un nouveau mot de passe trop court ou identique, sans rien modifier', function () {
    ouvrirSession($this->awa, 'ailleurs');
    $hash = $this->awa->fresh()->password;
    $page = monAcces($this->awa);

    $page->set('current_password', 'faux')->set('password', 'nouveaumdp9')->set('password_confirmation', 'nouveaumdp9')
        ->call('changePassword')->assertHasErrors(['current_password']);
    $page->set('current_password', 'motdepasse1')->set('password', 'court')->set('password_confirmation', 'court')
        ->call('changePassword')->assertHasErrors(['password']);
    $page->set('current_password', 'motdepasse1')->set('password', 'motdepasse1')->set('password_confirmation', 'motdepasse1')
        ->call('changePassword')->assertHasErrors(['password']);
    $page->set('current_password', 'motdepasse1')->set('password', 'nouveaumdp9')->set('password_confirmation', 'autrechose9')
        ->call('changePassword')->assertHasErrors(['password']);

    expect($this->awa->fresh()->password)->toBe($hash)
        ->and(DB::table('sessions')->count())->toBe(2)
        ->and(IdentityEvent::query()->count())->toBe(0);
});

it('limite les tentatives de mot de passe actuel', function () {
    RateLimiter::clear('mon-acces:'.$this->awa->id);
    $page = monAcces($this->awa);

    foreach (range(1, 5) as $_) {
        $page->set('current_password', 'faux')->set('password', 'nouveaumdp9')->set('password_confirmation', 'nouveaumdp9')->call('changePassword')->assertHasErrors(['current_password']);
    }
    $page->set('current_password', 'motdepasse1')->call('changePassword')->assertHasErrors(['current_password']);

    expect(Hash::check('motdepasse1', $this->awa->fresh()->password))->toBeTrue();
});

it('liste mes sessions valides, sans identifiant réel, avec l’appareil courant signalé', function () {
    ouvrirSession($this->awa, 'iphone', time() - 60, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1');
    ouvrirSession($this->awa, 'expiree', time() - 3 * 3600);
    ouvrirSession(person('Yao'), 'autrui');

    $page = monAcces($this->awa)->assertSee('Cet appareil')->assertSee('Safari · iOS')->assertSee('Chrome · Windows');

    expect($page->html())->not->toContain('iphone')->not->toContain('autrui')->not->toContain('expiree');
});

it('révoque une de mes sessions, jamais la courante ni celle d’une autre personne', function () {
    ouvrirSession($this->awa, 'ailleurs');
    $yao = person('Yao');
    ouvrirSession($yao, 'autrui');
    $sessions = app(Sessions::class);

    $page = monAcces($this->awa);
    $mine = $sessions->for($this->awa, courante());
    $ailleurs = $mine->firstWhere('current', false)->handle;
    $courante = $mine->firstWhere('current', true)->handle;

    $page->call('revoke', $courante);
    expect(DB::table('sessions')->where('id', courante())->exists())->toBeTrue();

    // Celle de Yao, désignée par sa propre poignée, est inatteignable pour Awa.
    $page->call('revoke', $sessions->for($yao, null)->first()->handle);
    expect(DB::table('sessions')->where('id', 'autrui')->exists())->toBeTrue();

    $page->call('revoke', $ailleurs)->assertSee('Appareil déconnecté');
    expect(DB::table('sessions')->where('id', 'ailleurs')->exists())->toBeFalse()
        ->and(IdentityEvent::query()->where('type', IdentityEvent::SESSION_REVOKED)->count())->toBe(1);
});

it('déconnecte tous les autres appareils après confirmation du mot de passe', function () {
    ouvrirSession($this->awa, 'a');
    ouvrirSession($this->awa, 'b');
    $page = monAcces($this->awa);

    $page->set('confirm_password', 'faux')->call('revokeOthers')->assertHasErrors(['confirm_password']);
    expect(DB::table('sessions')->count())->toBe(3);

    $page->set('confirm_password', 'motdepasse1')->call('revokeOthers')->assertHasNoErrors()->assertSee('2 autre(s) appareil(s)');
    expect(DB::table('sessions')->pluck('id')->all())->toBe([courante()])
        ->and(IdentityEvent::query()->where('type', IdentityEvent::OTHER_SESSIONS_REVOKED)->sole()->context)->toBe(['sessions' => 2]);
});

it('révoque toutes les sessions quand le mot de passe est réinitialisé par e-mail', function () {
    ouvrirSession($this->awa, 'a');
    ouvrirSession($this->awa, 'b');
    $autre = person('Yao');
    ouvrirSession($autre, 'autrui');

    app(ResetUserPassword::class)->reset($this->awa, ['password' => 'reinitialise9', 'password_confirmation' => 'reinitialise9']);

    expect(Hash::check('reinitialise9', $this->awa->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->pluck('id')->all())->toBe(['autrui'])
        ->and(IdentityEvent::query()->where('type', IdentityEvent::PASSWORD_RESET)->sole()->context)->toBe(['sessions_revoked' => 2]);
});

it('annonce honnêtement que la liste est indisponible hors du pilote « database »', function () {
    config(['session.driver' => 'file']);

    monAcces($this->awa)->assertSee('n’est pas disponible');
});

it('n’est accessible qu’à une personne connectée', function () {
    $this->get(route('espace'))->assertRedirect();
});

it('rend le journal des événements d’identité immuable, dans le modèle comme dans la base', function () {
    $event = IdentityEvent::record($this->awa->id, IdentityEvent::PASSWORD_CHANGED);

    expect(fn () => $event->update(['type' => 'autre']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class)
        ->and(fn () => DB::table('identity_events')->where('id', $event->id)->update(['type' => 'autre']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('identity_events')->delete())->toThrow(QueryException::class);
});
