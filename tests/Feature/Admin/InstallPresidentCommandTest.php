<?php

use App\Console\Commands\InstallPresident;
use App\Enums\PlatformRole;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => InstallPresident::readPasswordUsing(fn () => 'un-mot-de-passe-solide'));
afterEach(fn () => InstallPresident::readPasswordUsing(null));

it('crée le compte du Président, vérifié et super administrateur, et l’inscrit au journal', function () {
    putenv('SUDO_USER=root');

    try {
        $this->artisan('gamad:installer-president', ['--name' => 'Koné Djakaridja', '--email' => 'Zakaria@Exemple.org'])
            ->expectsOutputToContain('Compte du Président créé')
            ->assertSuccessful();
    } finally {
        putenv('SUDO_USER');
    }

    $user = User::sole();
    expect($user->name)->toBe('Koné Djakaridja')
        ->and($user->email)->toBe('zakaria@exemple.org')
        ->and($user->platform_role)->toBe(PlatformRole::SuperAdmin)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('un-mot-de-passe-solide', $user->password))->toBeTrue();

    $entry = AdminAction::sole();
    expect($entry->action)->toBe('installation.super_admin_created')
        ->and($entry->actor_label)->toBe('Installation')
        ->and($entry->actor_id)->toBeNull()
        ->and($entry->subject_id)->toBe($user->id)
        ->and($entry->payload)->toEqual([
            'new_role' => 'super_admin',
            'email_verified' => true,
            'system_user' => posix_getpwuid(posix_geteuid())['name'],
            'origin_account' => 'root',
            'machine' => gethostname(),
        ]);
});

it('n’écrit jamais le mot de passe au journal, ni en clair dans la base', function () {
    $this->artisan('gamad:installer-president', ['--name' => 'Président', '--email' => 'p@exemple.org'])->assertSuccessful();

    expect(json_encode(AdminAction::all()))->not->toContain('un-mot-de-passe-solide')
        ->and(User::sole()->password)->not->toContain('un-mot-de-passe-solide');
});

it('refuse des informations invalides sans rien créer', function (array $options, ?string $motDePasse, string $message) {
    InstallPresident::readPasswordUsing(fn () => $motDePasse ?? '');

    $this->artisan('gamad:installer-president', $options)->expectsOutputToContain($message)->assertFailed();

    expect(User::count())->toBe(0)->and(AdminAction::count())->toBe(0);
})->with([
    'nom absent' => [['--email' => 'p@exemple.org'], 'un-mot-de-passe-solide', 'nom affiché'],
    'e-mail invalide' => [['--name' => 'P', '--email' => 'pas-un-email'], 'un-mot-de-passe-solide', 'adresse e-mail'],
    'mot de passe trop court' => [['--name' => 'P', '--email' => 'p@exemple.org'], 'court', 'mot de passe'],
    'mot de passe absent' => [['--name' => 'P', '--email' => 'p@exemple.org'], null, 'mot de passe'],
]);

it('est rejouable : un Président déjà créé n’est ni recréé ni réinscrit au journal', function () {
    $this->artisan('gamad:installer-president', ['--name' => 'Président', '--email' => 'p@exemple.org'])->assertSuccessful();

    $this->artisan('gamad:installer-president', ['--name' => 'Autre nom', '--email' => 'P@EXEMPLE.ORG'])
        ->expectsOutputToContain('existe déjà')
        ->assertSuccessful();

    expect(User::count())->toBe(1)->and(AdminAction::count())->toBe(1)->and(User::sole()->name)->toBe('Président');
});

it('refuse de promouvoir en silence un compte ordinaire qui a déjà cette adresse', function () {
    User::factory()->create(['email' => 'p@exemple.org']);

    $this->artisan('gamad:installer-president', ['--name' => 'Président', '--email' => 'p@exemple.org'])
        ->expectsOutputToContain('pas super administrateur')
        ->assertFailed();

    expect(User::sole()->isSuperAdmin())->toBeFalse()->and(AdminAction::count())->toBe(0);
});
