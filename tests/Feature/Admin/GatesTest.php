<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

it('refuse les Gates super-admin et moderate à un membre ordinaire', function () {
    $member = User::factory()->create();

    expect(Gate::forUser($member)->allows('super-admin'))->toBeFalse()
        ->and(Gate::forUser($member)->allows('moderate'))->toBeFalse();
});

it('accorde moderate, mais pas super-admin, à un modérateur', function () {
    $moderator = User::factory()->moderator()->create();

    expect(Gate::forUser($moderator)->allows('moderate'))->toBeTrue()
        ->and(Gate::forUser($moderator)->allows('super-admin'))->toBeFalse();
});

it('accorde les deux Gates au super administrateur', function () {
    $admin = User::factory()->superAdmin()->create();

    expect(Gate::forUser($admin)->allows('super-admin'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('moderate'))->toBeTrue();
});

it('refuse les deux Gates à un invité', function () {
    expect(Gate::allows('super-admin'))->toBeFalse()
        ->and(Gate::allows('moderate'))->toBeFalse();
});

it('refuse en base un rôle de plateforme inconnu', function () {
    $user = User::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::table('users')->where('id', $user->id)->update(['platform_role' => 'roi'])))
        ->toThrow(QueryException::class);
});

it('ne permet pas de changer son rôle par assignation de masse', function () {
    $user = User::factory()->create();

    $user->update(['platform_role' => 'super_admin']);

    expect($user->fresh()->isSuperAdmin())->toBeFalse();
});
