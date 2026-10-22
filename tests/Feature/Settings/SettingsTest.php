<?php

use App\Models\AdminAction;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function reglages(): Settings
{
    return app(Settings::class);
}

it('rend les valeurs par défaut tant que rien n’est enregistré', function () {
    expect(reglages()->get('app_name'))->toBe('GAMAD')
        ->and(reglages()->get('limit_register_per_hour'))->toBe(20)
        ->and(reglages()->get('limit_login_per_minute'))->toBe(5)
        ->and(reglages()->get('mail_port'))->toBe(587)
        ->and(reglages()->get('mail_encryption'))->toBe('starttls')
        ->and(reglages()->get('mail_host'))->toBeNull()
        ->and(reglages()->hasSecret('mail_password'))->toBeFalse();
});

it('enregistre et relit des valeurs typées', function () {
    $admin = User::factory()->superAdmin()->create();

    $modifies = reglages()->update(['mail_host' => ' smtp.exemple.org ', 'mail_port' => '465', 'limit_login_per_minute' => '8'], $admin);

    expect($modifies)->toBe(['mail_host', 'mail_port', 'limit_login_per_minute'])
        ->and(reglages()->get('mail_host'))->toBe('smtp.exemple.org')
        ->and(reglages()->get('mail_port'))->toBe(465)
        ->and(reglages()->get('limit_login_per_minute'))->toBe(8);
});

it('refuse un réglage inconnu', function () {
    expect(fn () => reglages()->update(['reglage_invente' => 'x'], User::factory()->superAdmin()->create()))
        ->toThrow(InvalidArgumentException::class);
});

it('chiffre le mot de passe avec la clé de l’application et ne le rend jamais en clair', function () {
    $admin = User::factory()->superAdmin()->create();

    reglages()->update(['mail_password' => 'Sup3r-secret-SMTP'], $admin);

    $stocke = DB::table('settings')->where('key', 'mail_password')->first();

    expect($stocke->is_secret)->toBeTrue()
        ->and($stocke->value)->not->toContain('Sup3r-secret-SMTP')
        ->and(Crypt::decryptString($stocke->value))->toBe('Sup3r-secret-SMTP')
        ->and(reglages()->secret('mail_password'))->toBe('Sup3r-secret-SMTP')
        ->and(reglages()->hasSecret('mail_password'))->toBeTrue()
        ->and(json_encode(reglages()->values()))->not->toContain('Sup3r-secret-SMTP')
        ->and(reglages()->values())->not->toHaveKey('mail_password');

    expect(fn () => reglages()->get('mail_password'))->toThrow(LogicException::class);
});

it('inscrit au journal l’ancienne et la nouvelle valeur d’un réglage non secret', function () {
    $admin = User::factory()->superAdmin()->create(['name' => 'Le Président']);
    reglages()->update(['app_name' => 'Réseau GAMAD'], $admin);

    reglages()->update(['app_name' => 'GAMAD Action'], $admin);

    $entrees = AdminAction::where('action', 'setting.changed')->get();

    expect($entrees)->toHaveCount(2);
    $derniere = $entrees->firstWhere(fn ($e) => ($e->payload['nouveau'] ?? null) === 'GAMAD Action');
    expect($derniere->payload)->toEqual(['ancien' => 'Réseau GAMAD', 'nouveau' => 'GAMAD Action'])
        ->and($derniere->actor_id)->toBe($admin->id)
        ->and($derniere->actor_label)->toBe('Le Président')
        ->and($derniere->subject_type)->toBe('setting')
        ->and($derniere->subject_id)->toBe('app_name');
});

it('inscrit un secret au journal seulement comme « modifié », sans aucune valeur', function () {
    $admin = User::factory()->superAdmin()->create();

    reglages()->update(['mail_password' => 'PremierSecret-123'], $admin);
    reglages()->update(['mail_password' => 'DeuxiemeSecret-456'], $admin);

    $entrees = AdminAction::where('subject_id', 'mail_password')->get();
    expect($entrees)->toHaveCount(2);

    foreach ($entrees as $entree) {
        expect($entree->payload)->toEqual(['secret' => true, 'etat' => 'modifié']);
    }

    $tout = json_encode(DB::table('admin_actions')->get());
    expect($tout)->not->toContain('PremierSecret-123')->and($tout)->not->toContain('DeuxiemeSecret-456');
});

it('n’inscrit rien au journal quand la valeur ne change pas', function () {
    $admin = User::factory()->superAdmin()->create();
    reglages()->update(['app_name' => 'GAMAD'], $admin);
    reglages()->update(['mail_port' => null, 'limit_register_per_hour' => 20], $admin);

    expect(AdminAction::count())->toBe(0)->and(DB::table('settings')->count())->toBe(0);
});

it('applique les réglages à la configuration de Laravel', function (string $chiffrement, array $attendu) {
    $admin = User::factory()->superAdmin()->create();
    reglages()->update([
        'app_name' => 'Réseau GAMAD',
        'app_url' => 'https://reseau.exemple.org',
        'mail_host' => 'smtp.exemple.org',
        'mail_port' => 465,
        'mail_encryption' => $chiffrement,
        'mail_username' => 'no-reply@exemple.org',
        'mail_password' => 'secret-SMTP-1',
        'mail_from_address' => 'no-reply@exemple.org',
        'mail_from_name' => 'GAMAD',
        'limit_register_per_hour' => 3,
        'limit_login_per_minute' => 7,
    ], $admin);

    config(['mail.default' => 'log']);
    reglages()->applyToConfig();

    expect(config('app.name'))->toBe('Réseau GAMAD')
        ->and(config('app.url'))->toBe('https://reseau.exemple.org')
        ->and(config('mail.default'))->toBe('smtp')
        ->and(config('mail.from.address'))->toBe('no-reply@exemple.org')
        ->and(config('mail.from.name'))->toBe('GAMAD')
        ->and(config('gamad.limits.register_per_hour'))->toBe(3)
        ->and(config('gamad.limits.login_per_minute'))->toBe(7);

    $smtp = config('mail.mailers.smtp');
    expect($smtp)->toMatchArray($attendu + [
        'host' => 'smtp.exemple.org', 'port' => 465, 'username' => 'no-reply@exemple.org', 'password' => 'secret-SMTP-1',
    ]);
})->with([
    'SSL/TLS' => ['ssl', ['scheme' => 'smtps']],
    'STARTTLS' => ['starttls', ['scheme' => 'smtp', 'require_tls' => true]],
    'aucun' => ['none', ['scheme' => 'smtp', 'auto_tls' => false]],
]);

it('laisse l’envoi des courriels dans le journal tant que le serveur SMTP n’est pas renseigné', function () {
    config(['mail.default' => 'log']);

    reglages()->applyToConfig();

    expect(config('mail.default'))->toBe('log');
});

it('ne plante jamais au démarrage quand la table des réglages est absente', function () {
    DB::beginTransaction();
    Schema::drop('settings');

    try {
        app(Settings::class)->applyToConfig();
        expect(true)->toBeTrue();
    } finally {
        DB::rollBack();
    }

    expect(Schema::hasTable('settings'))->toBeTrue();
});

it('applique les limites enregistrées aux inscriptions et aux connexions', function () {
    reglages()->update(['limit_register_per_hour' => 2, 'limit_login_per_minute' => 2], User::factory()->superAdmin()->create());
    reglages()->applyToConfig();

    foreach ([1, 2] as $n) {
        $this->post('/register', ['name' => "P$n", 'email' => "p$n@example.com", 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1'])
            ->assertRedirect(route('fil'));
        $this->post('/logout');
    }
    $this->post('/register', ['name' => 'P3', 'email' => 'p3@example.com', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1'])
        ->assertStatus(429);

    $user = User::factory()->create(['password' => 'motdepasse1']);
    foreach ([1, 2] as $_) {
        $this->post('/login', ['email' => $user->email, 'password' => 'faux']);
    }
    $this->post('/login', ['email' => $user->email, 'password' => 'motdepasse1'])->assertStatus(429);
});
