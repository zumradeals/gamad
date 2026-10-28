<?php

use App\Core\Files\FilePurge;
use App\Core\Files\FilePurger;
use App\Core\Identity\AccountCloser;
use App\Core\Identity\Closure\AccountClosureParticipant;
use App\Core\Identity\Closure\ClosureContext;
use App\Core\Identity\IdentityEvent;
use App\Enums\LedgerKind;
use App\Livewire\Espace;
use App\Models\AdCampaign;
use App\Models\Company;
use App\Models\Profile;
use App\Models\User;
use App\Services\AccountClosure\ProfileClosure;
use App\Services\CompanyRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Fermeture d'un compte (Core\Identity) : tout ou rien en base, fichiers effacés seulement après validation, reprise en cas d'arrêt.

beforeEach(function () {
    Storage::fake('local');
    $this->user = person('Awa', ['Couture']);
    $this->user->forceFill(['password' => 'motdepasse1'])->save();
});

function entrepriseAvecFichiers(User $owner): Company
{
    $company = app(CompanyRegistry::class)->submit($owner, [
        'name' => 'Atelier Awa', 'legal_name' => 'Atelier Awa SARL', 'registration_country' => 'CI',
        'registration_number' => 'CI-ABJ-'.str_replace('-', '', $owner->id), 'sector' => 'Couture', 'city' => 'Abidjan', 'summary' => 'Ateliers.',
    ], ['rccm' => UploadedFile::fake()->create('registre.pdf', 80, 'application/pdf')]);
    $company->forceFill(['logo_path' => 'company-branding/logo-awa.png'])->save();
    Storage::disk('local')->put('company-branding/logo-awa.png', 'x');

    return $company->fresh();
}

it('refuse la suppression directe d’un compte : seule la fermeture contrôlée existe', function () {
    expect(fn () => $this->user->delete())->toThrow(LogicException::class);

    expect(User::find($this->user->id))->not->toBeNull();
});

it('ferme le compte, trace l’événement sans donnée personnelle et efface sessions et jetons de réinitialisation', function () {
    DB::table('password_reset_tokens')->insert(['email' => $this->user->email, 'token' => 'x', 'created_at' => now()]);
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => $this->user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

    fermerCompte($this->user);

    expect(User::find($this->user->id))->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);

    $event = IdentityEvent::query()->where('user_id', $this->user->id)->sole();
    expect($event->type)->toBe(IdentityEvent::ACCOUNT_CLOSED)
        ->and(json_encode($event->context))->not->toContain('10.0.0.1')->not->toContain($this->user->email);
});

it('refuse la fermeture d’un compte au portefeuille non vide avant tout effet, fichiers compris', function () {
    legacyExperience();
    $this->user->profile->update(['photo_path' => 'profile-photos/awa.jpg']);
    Storage::disk('local')->put('profile-photos/awa.jpg', 'x');
    portefeuilles()->post(portefeuilles()->forPerson($this->user), LedgerKind::Recharge, 300);

    Livewire::actingAs($this->user)->test(Espace::class)->call('show', 'compte')->set('password', 'motdepasse1')->call('deleteAccount')
        ->assertHasErrors(['password'])->assertSee('reversement');

    expect(User::find($this->user->id))->not->toBeNull()
        ->and(Auth::check())->toBeTrue()
        ->and(FilePurge::query()->count())->toBe(0)
        ->and(IdentityEvent::query()->count())->toBe(0);
    Storage::disk('local')->assertExists('profile-photos/awa.jpg');
});

it('n’efface aucun fichier et ne supprime rien quand la fermeture échoue en cours de route', function () {
    $this->user->profile->update(['photo_path' => 'profile-photos/awa.jpg']);
    Storage::disk('local')->put('profile-photos/awa.jpg', 'x');

    $closer = new AccountCloser([
        app(ProfileClosure::class),
        new class implements AccountClosureParticipant
        {
            public function assertCanClose(User $user): void {}

            public function beforeDelete(User $user, ClosureContext $context): void {}

            public function afterDelete(User $user, ClosureContext $context): void
            {
                throw new RuntimeException('panne simulée après la suppression de la ligne');
            }
        },
    ], app(FilePurger::class));

    expect(fn () => $closer->close($this->user))->toThrow(RuntimeException::class);

    expect(User::find($this->user->id))->not->toBeNull()
        ->and(Profile::query()->where('user_id', $this->user->id)->exists())->toBeTrue()
        ->and(FilePurge::query()->count())->toBe(0)
        ->and(IdentityEvent::query()->count())->toBe(0);
    Storage::disk('local')->assertExists('profile-photos/awa.jpg');
});

it('efface les fichiers après validation : photo, publication, candidature, entreprise, image de campagne', function () {
    $this->user->profile->update(['photo_path' => 'profile-photos/awa.jpg']);
    Storage::disk('local')->put('profile-photos/awa.jpg', 'x');
    $company = entrepriseAvecFichiers($this->user);
    $document = $company->documents()->first()->private_path;
    publication($this->user)->forceFill(['media_path' => 'publication-media/m.jpg'])->save();
    Storage::disk('local')->put('publication-media/m.jpg', 'x');
    AdCampaign::query()->forceCreate(['user_id' => $this->user->id, 'image_path' => 'ad-images/a.jpg']);
    Storage::disk('local')->put('ad-images/a.jpg', 'x');

    fermerCompte($this->user);

    foreach (['profile-photos/awa.jpg', $document, 'company-branding/logo-awa.png', 'publication-media/m.jpg', 'ad-images/a.jpg'] as $path) {
        Storage::disk('local')->assertMissing($path);
    }
    expect(FilePurge::query()->count())->toBe(0)->and(Company::query()->count())->toBe(0);
});

it('garde les fichiers à reprendre quand l’effacement est interrompu, puis les efface à la reprise', function () {
    $this->user->profile->update(['photo_path' => 'profile-photos/awa.jpg']);
    Storage::disk('local')->put('profile-photos/awa.jpg', 'x');

    // Le disque refuse l'effacement : la fermeture réussit quand même, le fichier reste désigné.
    $disk = Mockery::mock(Storage::disk('local'))->makePartial();
    $disk->shouldReceive('delete')->andReturn(false);
    Storage::set('local', $disk);

    fermerCompte($this->user);

    expect(User::find($this->user->id))->toBeNull();
    $purge = FilePurge::query()->sole();
    expect($purge->path)->toBe('profile-photos/awa.jpg')->and($purge->attempts)->toBe(1)->and($purge->last_error)->not->toBeNull();
    Storage::disk('local')->assertExists('profile-photos/awa.jpg');

    Storage::fake('local');
    Storage::disk('local')->put('profile-photos/awa.jpg', 'x');
    $this->artisan('gamad:purger-fichiers-en-attente')->assertSuccessful();

    Storage::disk('local')->assertMissing('profile-photos/awa.jpg');
    expect(FilePurge::query()->count())->toBe(0);
});

it('refuse un chemin de fichier qui sortirait du disque', function () {
    expect(fn () => app(FilePurger::class)->enqueue([['local', '../.env']]))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(FilePurger::class)->enqueue([['local', '/etc/passwd']]))->toThrow(InvalidArgumentException::class);
});

it('déconnecte sans recréer le compte même avec un jeton « se souvenir de moi »', function () {
    $this->user->forceFill(['remember_token' => 'jeton-existant'])->save();

    Livewire::actingAs($this->user)->test(Espace::class)->set('password', 'motdepasse1')->call('deleteAccount')->assertRedirect('/');

    expect(User::query()->count())->toBe(0);
});

it('journalise la suppression d’un compte de modération dans la transaction de fermeture', function () {
    $moderator = User::factory()->moderator()->create(['password' => 'motdepasse1']);

    Livewire::actingAs($moderator)->test(Espace::class)->set('password', 'motdepasse1')->call('deleteAccount');

    expect(User::find($moderator->id))->toBeNull()
        ->and(DB::table('admin_actions')->where('action', 'account.deleted_by_owner')->count())->toBe(1);
});

it('n’efface aucun fichier tant que la transaction englobante n’est pas validée', function () {
    $this->user->profile->update(['photo_path' => 'profile-photos/awa.jpg']);
    Storage::disk('local')->put('profile-photos/awa.jpg', 'x');

    DB::transaction(function () {
        fermerCompte($this->user);

        // Fermeture exécutée, transaction externe encore ouverte : le fichier est désigné, pas effacé.
        Storage::disk('local')->assertExists('profile-photos/awa.jpg');
        expect(FilePurge::query()->where('path', 'profile-photos/awa.jpg')->exists())->toBeTrue();
    });

    Storage::disk('local')->assertMissing('profile-photos/awa.jpg');
    expect(FilePurge::query()->count())->toBe(0)->and(User::find($this->user->id))->toBeNull();
});

it('préserve le compte, les données et les fichiers quand la transaction englobante est annulée après la fermeture', function () {
    $this->user->profile->update(['photo_path' => 'profile-photos/awa.jpg']);
    Storage::disk('local')->put('profile-photos/awa.jpg', 'x');
    $company = entrepriseAvecFichiers($this->user);
    $document = $company->documents()->first()->private_path;
    publication($this->user)->forceFill(['media_path' => 'publication-media/m.jpg'])->save();
    Storage::disk('local')->put('publication-media/m.jpg', 'x');
    AdCampaign::query()->forceCreate(['user_id' => $this->user->id, 'image_path' => 'ad-images/a.jpg']);
    Storage::disk('local')->put('ad-images/a.jpg', 'x');
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => $this->user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

    try {
        DB::transaction(function () {
            fermerCompte($this->user);

            throw new RuntimeException('annulation de la transaction englobante');
        });
    } catch (RuntimeException) {
    }

    expect(User::find($this->user->id))->not->toBeNull()
        ->and(Profile::query()->where('user_id', $this->user->id)->exists())->toBeTrue()
        ->and(Company::query()->where('owner_id', $this->user->id)->exists())->toBeTrue()
        ->and(DB::table('publications')->where('author_id', $this->user->id)->exists())->toBeTrue()
        ->and(AdCampaign::query()->where('user_id', $this->user->id)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 's1')->exists())->toBeTrue()
        ->and(FilePurge::query()->count())->toBe(0)
        ->and(IdentityEvent::query()->count())->toBe(0);

    foreach (['profile-photos/awa.jpg', $document, 'company-branding/logo-awa.png', 'publication-media/m.jpg', 'ad-images/a.jpg'] as $path) {
        Storage::disk('local')->assertExists($path);
    }
});
