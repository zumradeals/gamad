<?php

use App\Core\Execution\BackgroundRunner;
use App\Core\Execution\Execution;
use App\Core\Execution\ExecutionRun;
use App\Core\Execution\Instrumented;
use App\Core\Execution\Stats;
use App\Http\Middleware\RunBackgroundWork;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Exécution du Core : file persistante de Laravel (table jobs), passages bornés, reprise, idempotence, mesure.

class ProbeJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public static array $log = [];

    public static int $failuresLeft = 0;

    public array $runMetrics = [];

    public int $tries = 3;

    public function __construct(public string $name = 'a', public int $sleepMs = 0) {}

    public function backoff(): array
    {
        return [30, 60];
    }

    public function middleware(): array
    {
        return [new Instrumented];
    }

    public function handle(): void
    {
        if (self::$failuresLeft > 0) {
            self::$failuresLeft--;
            throw new RuntimeException('incident simulé');
        }
        $this->sleepMs > 0 && usleep($this->sleepMs * 1000);
        self::$log[] = $this->name;
        $this->runMetrics = ['units' => 3];
    }
}

beforeEach(function () {
    ProbeJob::$log = [];
    ProbeJob::$failuresLeft = 0;
    Cache::flush();
    $this->runner = fn () => new BackgroundRunner;
});

function jobsInQueue(): int
{
    return DB::table('jobs')->count();
}

it('persiste la tâche dans la file de Laravel sans l’exécuter dans la requête', function () {
    Execution::dispatch(new ProbeJob('a'));

    expect(jobsInQueue())->toBe(1)->and(ProbeJob::$log)->toBe([])->and(app(BackgroundRunner::class)->due())->toBeTrue();
});

it('exécute la tâche persistée avec un exécuteur neuf, comme après un redémarrage, et la mesure', function () {
    Execution::dispatch(new ProbeJob('a'));
    app()->forgetInstance(BackgroundRunner::class);

    expect(($this->runner)()->run(10, 5))->toBe(1);

    expect(ProbeJob::$log)->toBe(['a'])->and(jobsInQueue())->toBe(0);
    $run = ExecutionRun::query()->sole();
    expect($run->job)->toBe('ProbeJob')->and($run->outcome)->toBe('success')->and($run->attempt)->toBe(1)->and($run->metrics)->toBe(['units' => 3])->and($run->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('reprend une tâche réservée par un processus interrompu, seulement après le délai de réservation', function () {
    Execution::dispatch(new ProbeJob('a'));
    // Un processus a réservé la tâche puis est mort : elle reste « réservée ».
    DB::table('jobs')->update(['reserved_at' => now()->getTimestamp(), 'attempts' => 1]);

    expect(app(BackgroundRunner::class)->due())->toBeFalse()->and(($this->runner)()->run(10, 5))->toBe(0);

    $this->travel(BackgroundRunner::retryAfter() + 1)->seconds();

    expect(app(BackgroundRunner::class)->due())->toBeTrue()->and(($this->runner)()->run(10, 5))->toBe(1)->and(ProbeJob::$log)->toBe(['a']);
});

it('reprend après une erreur avec un délai, enregistre l’échec puis la réussite, sans échec définitif', function () {
    ProbeJob::$failuresLeft = 1;
    Execution::dispatch(new ProbeJob('a'));

    ($this->runner)()->run(10, 5);
    expect(ProbeJob::$log)->toBe([])->and(jobsInQueue())->toBe(1)->and(app(BackgroundRunner::class)->due())->toBeFalse();

    $this->travel(31)->seconds();
    ($this->runner)()->run(10, 5);

    expect(ProbeJob::$log)->toBe(['a'])->and(jobsInQueue())->toBe(0)->and(DB::table('failed_jobs')->count())->toBe(0);
    $runs = ExecutionRun::query()->orderBy('attempt')->get();
    expect($runs->pluck('outcome')->all())->toBe(['failure', 'success'])->and($runs->pluck('attempt')->all())->toBe([1, 2])->and($runs[0]->error_code)->toBe('RuntimeException');
});

it('abandonne après le nombre d’essais et le dit : échec définitif visible, jamais un succès', function () {
    ProbeJob::$failuresLeft = 99;
    Execution::dispatch(new ProbeJob('a'));

    foreach ([0, 31, 61] as $wait) {
        $this->travel($wait)->seconds();
        ($this->runner)()->run(10, 5);
    }

    expect(jobsInQueue())->toBe(0)->and(DB::table('failed_jobs')->count())->toBe(1)->and(ProbeJob::$log)->toBe([]);
    $failed = app(Stats::class)->failed();
    expect($failed['total'])->toBe(1)->and($failed['recent'][0]['job'])->toBe('ProbeJob')->and($failed['recent'][0]['error'])->toBe('RuntimeException');
});

it('borne le volume d’un passage', function () {
    foreach (range(1, 20) as $i) {
        Execution::dispatch(new ProbeJob("j$i"));
    }

    expect(($this->runner)()->run(5, 30))->toBe(5)->and(jobsInQueue())->toBe(15);
});

it('borne la durée d’un passage', function () {
    foreach (range(1, 10) as $i) {
        Execution::dispatch(new ProbeJob("j$i", sleepMs: 400));
    }

    $done = ($this->runner)()->run(10, 1);

    expect($done)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(4)->and(jobsInQueue())->toBe(10 - $done);
});

it('n’autorise qu’un passage à la fois : un second processus passe son tour', function () {
    Execution::dispatch(new ProbeJob('a'));
    $lock = Cache::lock('execution:runner', 60);
    $lock->get();

    expect(($this->runner)()->run(10, 5))->toBe(0)->and(jobsInQueue())->toBe(1);

    $lock->release();

    expect(($this->runner)()->run(10, 5))->toBe(1);
});

it('limite la fréquence des passages opportunistes, sauf pour la requête qui vient de confier une tâche', function () {
    Execution::dispatch(new ProbeJob('a'));
    expect(($this->runner)()->opportunistic())->toBe(1);

    Execution::dispatch(new ProbeJob('b'));
    expect(($this->runner)()->opportunistic())->toBe(0)->and(jobsInQueue())->toBe(1)
        ->and(($this->runner)()->opportunistic(now: true))->toBe(1);

    $this->travel(BackgroundRunner::POLL + 1)->seconds();
    Execution::dispatch(new ProbeJob('c'));
    expect(($this->runner)()->opportunistic())->toBe(1);
});

it('fait avancer les tâches après la réponse d’une requête ordinaire, sans la ralentir ni la faire échouer', function () {
    Execution::dispatch(new ProbeJob('a'));

    $this->get('/')->assertOk();

    expect(ProbeJob::$log)->toBe(['a']);
});

it('ne fait jamais échouer la requête quand l’exécution échoue', function () {
    Execution::dispatch(new ProbeJob('a'));
    ProbeJob::$failuresLeft = 1;

    $this->get('/')->assertOk();

    expect(jobsInQueue())->toBe(1);
});

it('compte la file pour l’observation : à traiter, en attente de reprise, réservées', function () {
    Execution::dispatch(new ProbeJob('a'));
    Execution::dispatch(new ProbeJob('b'));
    DB::table('jobs')->where('id', DB::table('jobs')->min('id'))->update(['available_at' => now()->addMinutes(5)->getTimestamp()]);

    $queue = app(Stats::class)->queue();

    expect($queue['due'])->toBe(1)->and($queue['waiting'])->toBe(1)->and($queue['reserved'])->toBe(0);
});

it('active le middleware sur le groupe web', function () {
    expect(collect(app('router')->getMiddlewareGroups()['web'])->contains(RunBackgroundWork::class))->toBeTrue();
});
