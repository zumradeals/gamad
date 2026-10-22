<?php

use App\Enums\PlatformRole;
use App\Models\AdminAction;
use App\Models\User;

it('promeut un compte existant et inscrit l’acte au journal', function () {
    $user = User::factory()->create(['email' => 'president@example.com', 'name' => 'Koné']);

    $this->artisan('gamad:super-admin', ['email' => 'president@example.com'])
        ->expectsOutputToContain('est maintenant super administrateur')
        ->assertSuccessful();

    expect($user->fresh()->platform_role)->toBe(PlatformRole::SuperAdmin);

    $entry = AdminAction::sole();
    expect($entry->action)->toBe('platform_role.promoted_to_super_admin')
        ->and($entry->subject_id)->toBe($user->id)
        ->and($entry->actor_id)->toBeNull()
        ->and($entry->actor_label)->toBe('Console GAMAD')
        ->and($entry->payload)->toEqual(['previous_role' => 'member', 'new_role' => 'super_admin']);
});

it('échoue proprement si aucun compte ne correspond, sans rien créer', function () {
    $this->artisan('gamad:super-admin', ['email' => 'inconnu@example.com'])
        ->expectsOutputToContain('Aucun compte ne correspond')
        ->assertFailed();

    expect(User::count())->toBe(0)->and(AdminAction::count())->toBe(0);
});

it('ne double pas l’inscription au journal si le compte est déjà super administrateur', function () {
    User::factory()->superAdmin()->create(['email' => 'president@example.com']);

    $this->artisan('gamad:super-admin', ['email' => 'president@example.com'])
        ->expectsOutputToContain('déjà super administrateur')
        ->assertSuccessful();

    expect(AdminAction::count())->toBe(0);
});
