<?php

use App\Core\Files\FilePurge;
use App\Http\Middleware\ResumeFilePurges;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

// Reprise des effacements interrompus par l'application elle-même, sans planificateur serveur ni nouvelle fermeture de compte.

beforeEach(function () {
    Storage::fake('local');
    Cache::forget(ResumeFilePurges::KEY);
});

function enAttente(string $path): FilePurge
{
    Storage::disk('local')->put($path, 'x');

    return FilePurge::query()->create(['disk' => 'local', 'path' => $path, 'attempts' => 1, 'last_error' => 'Le disque a refusé l’effacement.']);
}

it('reprend les fichiers en attente après une requête ordinaire, sans nouvelle fermeture de compte', function () {
    enAttente('profile-photos/a.jpg');

    $this->get('/')->assertOk();

    Storage::disk('local')->assertMissing('profile-photos/a.jpg');
    expect(FilePurge::query()->count())->toBe(0);
});

it('ne reprend qu’une fois toutes les cinq minutes, puis de nouveau après ce délai', function () {
    enAttente('profile-photos/a.jpg');
    $this->get('/')->assertOk();

    enAttente('profile-photos/b.jpg');
    $this->get('/')->assertOk();
    Storage::disk('local')->assertExists('profile-photos/b.jpg');
    expect(FilePurge::query()->count())->toBe(1);

    $this->travel(ResumeFilePurges::EVERY_SECONDS + 1)->seconds();
    $this->get('/')->assertOk();

    Storage::disk('local')->assertMissing('profile-photos/b.jpg');
    expect(FilePurge::query()->count())->toBe(0);
});

it('n’empêche pas la réponse quand le disque refuse encore, et note la tentative', function () {
    $purge = enAttente('profile-photos/a.jpg');
    $disk = Mockery::mock(Storage::disk('local'))->makePartial();
    $disk->shouldReceive('delete')->andReturn(false);
    Storage::set('local', $disk);

    $this->get('/')->assertOk();

    expect($purge->fresh()->attempts)->toBe(2);
});

it('n’a rien à faire sans fichier en attente', function () {
    $this->get('/')->assertOk();

    expect(FilePurge::query()->count())->toBe(0)->and(Cache::has(ResumeFilePurges::KEY))->toBeTrue();
});
