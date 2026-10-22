<?php

use App\Exceptions\ImmutableRecordException;
use App\Models\AdminAction;
use App\Models\User;
use App\Services\AdminJournal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('inscrit un acte avec l’acteur, son nom copié, le sujet et le motif', function () {
    $admin = User::factory()->superAdmin()->create(['name' => 'Le Président']);
    $subject = User::factory()->create();

    $entry = AdminJournal::record($admin, 'user.suspended', $subject, 'Propos contraires à la charte', ['duration_days' => 7]);

    expect(Str::isUuid($entry->id))->toBeTrue()
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->actor_label)->toBe('Le Président')
        ->and($entry->action)->toBe('user.suspended')
        ->and($entry->subject_id)->toBe($subject->id)
        ->and($entry->reason)->toBe('Propos contraires à la charte')
        ->and($entry->payload)->toBe(['duration_days' => 7])
        ->and($entry->created_at)->not->toBeNull();
});

it('refuse de modifier une entrée du journal', function () {
    $entry = AdminJournal::record(User::factory()->superAdmin()->create(), 'test.action');

    $entry->action = 'test.truquee';

    expect(fn () => $entry->save())->toThrow(ImmutableRecordException::class);
    expect(fn () => $entry->update(['reason' => 'autre']))->toThrow(ImmutableRecordException::class);
    expect(AdminAction::find($entry->id)->action)->toBe('test.action');
});

it('refuse de supprimer une entrée du journal', function () {
    $entry = AdminJournal::record(User::factory()->superAdmin()->create(), 'test.action');

    expect(fn () => $entry->delete())->toThrow(ImmutableRecordException::class);
    expect(AdminAction::count())->toBe(1);
});

it('refuse aussi, au niveau de la base, les modifications et suppressions qui contournent le modèle', function () {
    $entry = AdminJournal::record(User::factory()->superAdmin()->create(), 'test.action');

    // Chaque tentative dans son point de sauvegarde : PostgreSQL abandonne la transaction après une erreur.
    expect(fn () => DB::transaction(fn () => DB::table('admin_actions')->where('id', $entry->id)->update(['action' => 'truque'])))
        ->toThrow(QueryException::class, 'ajout seul');
    expect(fn () => DB::transaction(fn () => DB::table('admin_actions')->where('id', $entry->id)->delete()))
        ->toThrow(QueryException::class, 'ajout seul');
    expect(fn () => DB::transaction(fn () => AdminAction::query()->delete()))->toThrow(QueryException::class);

    expect(AdminAction::find($entry->id)->action)->toBe('test.action');
});

it('conserve l’entrée et le nom de l’acteur quand son compte est supprimé', function () {
    $admin = User::factory()->superAdmin()->create(['name' => 'Ancien modérateur']);
    $entry = AdminJournal::record($admin, 'test.action');

    $admin->delete();

    $kept = AdminAction::find($entry->id);
    expect($kept)->not->toBeNull()
        ->and($kept->actor_id)->toBe($admin->id)
        ->and($kept->actor_label)->toBe('Ancien modérateur');
});
