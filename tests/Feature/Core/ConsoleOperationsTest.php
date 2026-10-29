<?php

use App\Core\Execution\BackgroundRunner;
use App\Core\Execution\ExecutionRun;
use App\Core\Identity\SecurityAccessEvent;
use App\Http\Middleware\RunBackgroundWork;
use App\Livewire\TraitementsCore;
use App\Models\AdminAction;
use App\Models\MatchingSearch;
use App\Models\User;
use App\Services\BackupStorage;
use App\Services\CoreConsoleActions;
use App\Services\CoreConsoleObservations;
use App\Services\MatchingConditions;
use App\Services\MatchingSearches;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('ne confond pas l’absence d’instrumentation, une file vide et une panne', function () {
    $snapshot = app(CoreConsoleObservations::class)->snapshot(24);
    expect($snapshot['measurement_errors'])->toBe([]);
    expect($snapshot['health']['Identity']['state'])->toBe('inconnu');
    expect($snapshot['health']['Execution']['state'])->toBe('inconnu');
    expect($snapshot['health']['Matching']['state'])->toBe('inconnu');
    expect($snapshot['health']['Sauvegardes']['state'])->toBe('non configuré');
    app(BackgroundRunner::class)->run(12, 8, 'console');
    $snapshot = app(CoreConsoleObservations::class)->snapshot(24);
    expect($snapshot['health']['Execution']['state'])->toBe('sain');
    expect($snapshot['runner']->job)->toBe('Runner:console');
    expect(json_decode($snapshot['runner']->metrics, true))->toEqual(['processed' => 0, 'contended' => 0, 'idle' => 1]);
    $this->travel(6)->minutes();
    expect(app(CoreConsoleObservations::class)->snapshot(24)['health']['Execution']['state'])->toBe('inconnu');
});

it('mesure un lanceur occupé sans prétendre avoir effectué du travail', function () {
    $lock = Cache::lock('execution:runner', 40);
    $lock->get();
    expect(app(BackgroundRunner::class)->run(12, 8, 'console'))->toBe(0);
    $record = ExecutionRun::where('job', 'Runner:console')->firstOrFail();
    expect($record->metrics['contended'])->toBe(1)->and($record->metrics['idle'])->toBe(0);
    $lock->release();
});

it('ne lance pas de traitement pendant l’action Livewire et exige une justification', function () {
    $admin = User::factory()->superAdmin()->create();
    $page = Livewire::actingAs($admin)->test(TraitementsCore::class);
    $page->call('requestRunner')->assertHasErrors('reason');
    $page->set('reason', 'Vérifier le passage après une intervention')->call('requestRunner')->assertSee('Passage borné demandé');
    expect(AdminAction::where('action', 'console.runner.request')->count())->toBe(1);
    expect(ExecutionRun::where('job', 'Runner:console')->count())->toBe(0);
});

it('relance par MatchingSearches avec les préférences actuelles et sans concurrence ou rerun parasite', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = person('Test relance', ['Couture']);
    $user->profile->update(['matching_preferences' => array_replace(MatchingConditions::defaults(), ['enabled' => true, 'remote' => true, 'intentions' => ['offer'], 'terms' => ['volunteer']])]);
    $search = MatchingSearch::where('user_id', $user->id)->firstOrFail();
    app(MatchingSearches::class)->fail($search->id, 'RuntimeException');
    $actions = app(CoreConsoleActions::class);
    expect($actions->retry($admin, $search->id, 'Reprise après réparation vérifiée')['result'])->toBe('requested');
    $next = MatchingSearch::where('user_id', $user->id)->where('state', 'queued')->firstOrFail();
    $next->forceFill(['state' => 'running'])->save();
    expect($actions->retry($admin, $search->id, 'Seconde demande de contrôle explicite')['result'])->toBe('already_open');
    expect($next->fresh()->rerun)->toBeFalse();
    expect(MatchingSearch::where('user_id', $user->id)->whereIn('state', ['queued', 'running'])->count())->toBe(1);
    app(MatchingSearches::class)->fail($next->id, 'RuntimeException');
    $prefs = $user->profile->matching_preferences;
    $prefs['enabled'] = false;
    $user->profile->forceFill(['matching_preferences' => $prefs])->save();
    expect($actions->retry($admin, $search->id, 'Vérification du consentement retiré')['result'])->toBe('refused_obsolete');
    expect(AdminAction::where('action', 'console.matching.retry')->count())->toBe(3);
});

it('refuse les interventions par service aux comptes ordinaires et modérateurs', function () {
    foreach ([User::factory()->create(), User::factory()->moderator()->create()] as $actor) {
        expect(fn () => app(CoreConsoleActions::class)->requestRunner($actor, 'Justification suffisamment longue'))->toThrow(AuthorizationException::class);
    }
    expect(AdminAction::where('action', 'console.runner.request')->count())->toBe(0);
});

it('n’expose aucun chemin de purge abandonnée et ne propose aucune remise à zéro', function () {
    DB::table('file_purges')->insert(['id' => (string) Str::uuid(), 'disk' => 'local', 'path' => 'private/secret-document.pdf', 'attempts' => 10, 'last_error' => 'private message', 'created_at' => now()]);
    Livewire::actingAs(User::factory()->superAdmin()->create())->test(TraitementsCore::class)->assertSee('1 abandonnée(s)')->assertDontSee('private/secret-document.pdf')->assertDontSee('private message');
    expect(DB::table('file_purges')->value('attempts'))->toBe(10);
});

it('mesure le coût pondéré par candidat avec des agrégats SQL et laisse le coût nul sans candidat', function () {
    foreach ([[100, 10], [900, 30], [40, 0]] as [$duration, $examined]) {
        ExecutionRun::create(['job' => 'RunMatchingSearch', 'attempt' => 1, 'outcome' => 'success', 'duration_ms' => $duration, 'metrics' => ['examined' => $examined], 'ran_at' => now()]);
    }
    $snapshot = app(CoreConsoleObservations::class)->snapshot(24);
    expect($snapshot['measurement_errors'])->toBe([]);
    expect((int) $snapshot['matching_cost'][0]->examined)->toBe(40);
    expect((float) $snapshot['matching_cost'][0]->ms_per_candidate)->toBe(26.0);
    expect($snapshot['runs'][0]['metrics']['examined'])->toBe(40);
});

it('lie le seuil des sauvegardes à la cadence confirmée et traite l’absence de signal comme inconnue', function () {
    $backup = Mockery::mock(BackupStorage::class);
    $backup->shouldReceive('configured')->andReturn(true);
    $status = ['fichier_present' => true, 'dernier_essai' => now()->subHours(37), 'derniere_reussite' => now()->subHours(37), 'dernier_echec' => null];
    $backup->shouldReceive('status')->andReturn($status);
    app()->instance(BackupStorage::class, $backup);
    expect(app(CoreConsoleObservations::class)->snapshot(24)['health']['Sauvegardes']['state'])->toBe('inconnu');
    config(['core_console.backup_expected_hours' => 24, 'core_console.backup_grace_hours' => 12]);
    expect(app(CoreConsoleObservations::class)->snapshot(24)['health']['Sauvegardes']['state'])->toBe('en panne');
    config(['core_console.backup_expected_hours' => 168]);
    expect(app(CoreConsoleObservations::class)->snapshot(24)['health']['Sauvegardes']['state'])->toBe('sain');
});

it('dégrade une tâche réellement disponible mais pas une tâche délibérément différée', function () {
    $row = ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->addHour()->timestamp, 'created_at' => now()->subHour()->timestamp];
    DB::table('jobs')->insert($row);
    expect(app(CoreConsoleObservations::class)->snapshot(24)['health']['Execution']['state'])->toBe('inconnu');
    DB::table('jobs')->update(['available_at' => now()->subMinutes(6)->timestamp]);
    expect(app(CoreConsoleObservations::class)->snapshot(24)['health']['Execution']['state'])->toBe('dégradé');
});

it('effectue le passage demandé seulement lors de la terminaison et journalise son résultat réel', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    app(CoreConsoleActions::class)->requestRunner($admin, 'Vérifier le lanceur après la réponse');
    expect(ExecutionRun::where('job', 'Runner:console')->count())->toBe(0);
    app(RunBackgroundWork::class)->terminate(request(), response('réponse déjà envoyée'));
    expect(ExecutionRun::where('job', 'Runner:console')->count())->toBe(1);
    expect(AdminAction::where('action', 'console.runner.result')->count())->toBe(1);
    expect(AdminAction::where('action', 'console.runner.result')->first()->payload['processed'])->toBe(0);
});

it('ne déclare pas saines des mesures d’identité et de Matching devenues anciennes', function () {
    SecurityAccessEvent::create(['occurred_at' => now()->subDays(2), 'outcome' => 'success']);
    ExecutionRun::create(['job' => 'RunMatchingSearch', 'attempt' => 1, 'outcome' => 'success', 'duration_ms' => 1, 'ran_at' => now()->subDays(2)]);
    $snapshot = app(CoreConsoleObservations::class)->snapshot(168);
    expect($snapshot['health']['Identity']['state'])->toBe('inconnu')->and($snapshot['health']['Matching']['state'])->toBe('inconnu');
});
