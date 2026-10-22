<?php

use App\Enums\PlatformRole;
use App\Models\AdminAction;
use App\Models\User;

const RAISON = 'Président de GAMAD, désigné le 22 octobre 2026';

it('promeut un compte existant et inscrit au journal l’acte, sa raison, le compte système et la machine', function () {
    $user = User::factory()->create(['email' => 'president@example.com', 'name' => 'Koné']);

    $this->artisan('gamad:super-admin', ['email' => 'president@example.com', '--raison' => RAISON])
        ->expectsOutputToContain('est maintenant super administrateur')
        ->assertSuccessful();

    expect($user->fresh()->platform_role)->toBe(PlatformRole::SuperAdmin);

    $entry = AdminAction::sole();
    expect($entry->action)->toBe('platform_role.promoted_to_super_admin')
        ->and($entry->subject_id)->toBe($user->id)
        ->and($entry->actor_id)->toBeNull()
        ->and($entry->actor_label)->toBe('Console GAMAD')
        ->and($entry->reason)->toBe(RAISON)
        ->and($entry->payload)->toEqual([
            'previous_role' => 'member',
            'new_role' => 'super_admin',
            'system_user' => get_current_user(),
            'machine' => gethostname(),
        ]);
});

it('exige l’option --raison et ne change rien sans elle', function (array $options) {
    $user = User::factory()->create(['email' => 'president@example.com']);

    $this->artisan('gamad:super-admin', ['email' => 'president@example.com'] + $options)
        ->expectsOutputToContain('--raison')
        ->assertFailed();

    expect($user->fresh()->platform_role)->toBe(PlatformRole::Member)
        ->and(AdminAction::count())->toBe(0);
})->with([
    'absente' => [[]],
    'vide' => [['--raison' => '']],
    'espaces seuls' => [['--raison' => '   ']],
]);

it('échoue proprement si aucun compte ne correspond, sans rien créer', function () {
    $this->artisan('gamad:super-admin', ['email' => 'inconnu@example.com', '--raison' => RAISON])
        ->expectsOutputToContain('Aucun compte ne correspond')
        ->assertFailed();

    expect(User::count())->toBe(0)->and(AdminAction::count())->toBe(0);
});

it('ne double pas l’inscription au journal si le compte est déjà super administrateur', function () {
    User::factory()->superAdmin()->create(['email' => 'president@example.com']);

    $this->artisan('gamad:super-admin', ['email' => 'president@example.com', '--raison' => RAISON])
        ->expectsOutputToContain('déjà super administrateur')
        ->assertSuccessful();

    expect(AdminAction::count())->toBe(0);
});
