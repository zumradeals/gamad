<?php

use App\Livewire\JournalAdministration;
use App\Livewire\ParametresCopie;
use App\Models\AdminAction;
use App\Models\User;
use App\Services\Backup\StorageProbe;
use App\Services\BackupStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** Sonde de remplacement : mémorise la configuration reçue et répond ce qu'on lui demande. */
class SondeDeTest implements StorageProbe
{
    public ?string $recue = null;

    public int $appels = 0;

    public function __construct(public ?string $refus = null) {}

    public function verify(string $rcloneConfig): ?string
    {
        $this->recue = $rcloneConfig;
        $this->appels++;

        return $this->refus;
    }
}

beforeEach(function () {
    Process::fake(['*' => Process::result(output: "MASQUE\n")]);
    $this->sonde = new SondeDeTest;
    app()->instance(StorageProbe::class, $this->sonde);
    $this->admin = User::factory()->superAdmin()->create(['name' => 'Président de test', 'password' => 'motdepasse-solide']);
    $this->mod = User::factory()->moderator()->create(['name' => 'Modératrice Une']);
    $this->etat = sys_get_temp_dir().'/gamad-copie-etat-'.bin2hex(random_bytes(4));
    config(['gamad.backup.state_file' => $this->etat]);
});

afterEach(function () {
    @unlink($this->etat);
});

function acces(array $remplace = []): array
{
    return $remplace + ['endpoint' => 'https://s3.exemple.org', 'bucket' => 'gamad-copies', 'region' => '', 'access_key' => 'CLE-D-ACCES-1234', 'secret_key' => 'CLE-SECRETE-5678'];
}

function stockage(): BackupStorage
{
    return app(BackupStorage::class);
}

// ─── Enregistrer les accès ───────────────────────────────────────────────────

it('essaie les accès, les enregistre chiffrés, crée la phrase de secours et inscrit tout au journal', function () {
    expect(stockage()->configured())->toBeFalse();

    $phrase = stockage()->save($this->admin, acces());

    expect($phrase)->toMatch('/^[A-Za-z0-9]{4}(-[A-Za-z0-9]{4}){7}$/')->and(stockage()->configured())->toBeTrue()->and($this->sonde->appels)->toBe(1);

    // La sonde reçoit la vraie configuration, avec le chiffrement, mais jamais la phrase en clair.
    expect($this->sonde->recue)->toContain('[gamad-s3]')->toContain('endpoint = https://s3.exemple.org')->toContain('remote = gamad-s3:gamad-copies/gamad-reseau')
        ->toContain('[gamad-copie]')->toContain('type = crypt')->toContain('password = MASQUE')->not->toContain($phrase);

    // En base : clés et phrase chiffrées, jamais lisibles.
    $lignes = DB::table('settings')->whereIn('key', ['backup_s3_access_key', 'backup_s3_secret_key', 'backup_passphrase'])->pluck('value', 'key');
    expect($lignes)->toHaveCount(3);
    foreach ($lignes as $valeur) {
        expect($valeur)->not->toContain('CLE-D-ACCES')->not->toContain('CLE-SECRETE')->not->toContain($phrase);
    }
    expect(DB::table('settings')->where('key', 'backup_s3_bucket')->value('value'))->toBe('gamad-copies');

    // Au journal : une ligne par réglage modifié, sans valeur pour les secrets.
    $journal = AdminAction::query()->where('action', 'setting.changed')->get();
    expect($journal->pluck('subject_id')->all())->toContain('backup_s3_endpoint', 'backup_s3_bucket', 'backup_s3_access_key', 'backup_s3_secret_key', 'backup_passphrase');
    foreach ($journal->whereIn('subject_id', ['backup_s3_access_key', 'backup_s3_secret_key', 'backup_passphrase']) as $acte) {
        expect($acte->payload)->toEqual(['secret' => true, 'etat' => 'modifié'])->and($acte->actor_id)->toBe($this->admin->id);
    }
    expect(json_encode(AdminAction::all()->pluck('payload')))->not->toContain('CLE-SECRETE')->not->toContain($phrase);
});

it('n’enregistre rien quand l’essai d’écriture échoue', function () {
    $this->sonde->refus = 'Le stockage a refusé l’essai (AccessDenied).';

    $erreur = rescue(fn () => stockage()->save($this->admin, acces()), fn ($e) => $e, false);

    expect($erreur)->toBeInstanceOf(ValidationException::class)->and($erreur->errors()['storage'][0])->toContain('AccessDenied')->toContain('Rien n’a été enregistré');
    expect(DB::table('settings')->where('key', 'like', 'backup_%')->count())->toBe(0)->and(AdminAction::query()->count())->toBe(0)->and(stockage()->configured())->toBeFalse();
});

it('valide les accès avant tout essai', function (array $remplace, string $champ, string $message) {
    $erreur = rescue(fn () => stockage()->save($this->admin, acces($remplace)), fn ($e) => $e, false);

    expect($erreur)->toBeInstanceOf(ValidationException::class)->and($erreur->errors()[$champ][0])->toContain($message)->and($this->sonde->appels)->toBe(0);
    expect(DB::table('settings')->where('key', 'like', 'backup_%')->count())->toBe(0);
})->with([
    'adresse sans chiffrement' => [['endpoint' => 'http://s3.exemple.org'], 'endpoint', 'doit commencer par https://'],
    'adresse locale imitée' => [['endpoint' => 'http://127.0.0.1.exemple.org'], 'endpoint', 'doit commencer par https://'],
    'adresse vide' => [['endpoint' => ''], 'endpoint', 'doit commencer par https://'],
    'bucket invalide' => [['bucket' => 'Mauvais Nom/avec espace'], 'bucket', 'n’est pas valide'],
    'bucket trop court' => [['bucket' => 'a'], 'bucket', 'n’est pas valide'],
    'clé d’accès absente' => [['access_key' => ''], 'access_key', 'Indiquez la clé d’accès'],
    'clé d’accès avec espace' => [['access_key' => 'a b'], 'access_key', 'aucun espace'],
    'clé secrète absente' => [['secret_key' => ''], 'secret_key', 'Indiquez la clé secrète'],
    'clé secrète avec espace' => [['secret_key' => 'a b'], 'secret_key', 'aucun espace'],
    'région invalide' => [['region' => 'eu west!'], 'region', 'n’est pas valide'],
    'phrase trop courte' => [['passphrase' => 'courte'], 'passphrase', 'trop courte'],
]);

it('accepte une adresse locale pour les essais, et une phrase de secours existante pour relire des copies déjà faites', function () {
    $phrase = stockage()->save($this->admin, acces(['endpoint' => 'http://127.0.0.1:9000', 'passphrase' => 'ma-phrase-de-secours-longue']));

    expect($phrase)->toBeNull()->and(stockage()->configured())->toBeTrue();
    expect(stockage()->revealPassphrase($this->admin, 'motdepasse-solide'))->toBe('ma-phrase-de-secours-longue');
});

it('garde les clés quand les champs sont laissés vides, ne change pas la phrase et ne la montre plus', function () {
    $phrase = stockage()->save($this->admin, acces());
    $this->sonde->recue = null;

    $nouvelle = stockage()->save($this->admin, acces(['access_key' => '', 'secret_key' => '', 'bucket' => 'autre-bucket', 'region' => 'eu-west-1']));

    expect($nouvelle)->toBeNull()->and($this->sonde->recue)->toContain('access_key_id = CLE-D-ACCES-1234')->toContain('secret_access_key = CLE-SECRETE-5678')->toContain('region = eu-west-1')->toContain('gamad-s3:autre-bucket/');
    expect(stockage()->visible())->toBe(['endpoint' => 'https://s3.exemple.org', 'bucket' => 'autre-bucket', 'region' => 'eu-west-1']);
    expect(stockage()->revealPassphrase($this->admin, 'motdepasse-solide'))->toBe($phrase);
});

it('refuse de changer la phrase de secours, qui rendrait les copies déjà faites illisibles', function () {
    stockage()->save($this->admin, acces());

    $erreur = rescue(fn () => stockage()->save($this->admin, acces(['passphrase' => 'une-autre-phrase-de-secours'])), fn ($e) => $e, false);

    expect($erreur)->toBeInstanceOf(ValidationException::class)->and($erreur->errors()['passphrase'][0])->toContain('ne se change pas ici');
});

it('réserve les accès au super administrateur, même en appelant le service', function () {
    foreach ([$this->mod, User::factory()->create()] as $autre) {
        expect(fn () => stockage()->save($autre, acces()))->toThrow(AuthorizationException::class);
        expect(fn () => stockage()->revealPassphrase($autre, 'secret'))->toThrow(AuthorizationException::class);
    }

    expect(stockage()->configured())->toBeFalse()->and($this->sonde->appels)->toBe(0);
});

// ─── Phrase de secours ───────────────────────────────────────────────────────

it('réaffiche la phrase sur mot de passe, et l’inscrit au journal', function () {
    $phrase = stockage()->save($this->admin, acces());

    foreach ([null, '', 'mauvais-mot-de-passe'] as $essai) {
        expect(fn () => stockage()->revealPassphrase($this->admin, $essai))->toThrow(ValidationException::class, 'Mot de passe incorrect');
    }
    expect(AdminAction::query()->where('action', 'backup.passphrase_revealed')->count())->toBe(0);

    expect(stockage()->revealPassphrase($this->admin, 'motdepasse-solide'))->toBe($phrase);

    $acte = AdminAction::query()->where('action', 'backup.passphrase_revealed')->sole();
    expect($acte->actor_id)->toBe($this->admin->id)->and($acte->payload)->toBeNull();
});

it('ne réaffiche rien quand aucune phrase n’existe', function () {
    expect(fn () => stockage()->revealPassphrase($this->admin, 'motdepasse-solide'))->toThrow(ValidationException::class, 'Aucune phrase de secours');
});

// ─── Configuration pour le script, commande ──────────────────────────────────

it('refuse de construire la configuration tant que la copie n’est pas configurée', function () {
    expect(fn () => stockage()->rcloneConfig())->toThrow(RuntimeException::class, 'n’est pas configurée');
});

it('écrit la configuration rclone par la commande de l’application, et sort avec le code 2 sans configuration', function () {
    $this->artisan('gamad:copie-configuration')->assertExitCode(2);

    $phrase = stockage()->save($this->admin, acces());

    expect(Artisan::call('gamad:copie-configuration'))->toBe(0);
    $sortie = Artisan::output();
    expect($sortie)->toContain('[gamad-copie]')->toContain('access_key_id = CLE-D-ACCES-1234')->toContain('password = MASQUE')->not->toContain($phrase)->toBe(stockage()->rcloneConfig());
});

it('ne dit rien sur la sortie standard quand la copie n’est pas configurée', function () {
    $this->artisan('gamad:copie-configuration')->doesntExpectOutputToContain('[gamad-')->assertExitCode(2);
});

// ─── État écrit par la copie nocturne ────────────────────────────────────────

it('lit l’état écrit par le script', function () {
    $reussite = now()->subHours(3)->toIso8601String();
    $essai = now()->subDays(2)->toIso8601String();
    file_put_contents($this->etat, "derniere_reussite=$reussite\ndernier_essai=$essai\ndernier_echec=".now()->subDays(5)->toIso8601String()."|REFUS (copier) : trop vieux\n");

    $etat = stockage()->status();

    expect($etat['fichier_present'])->toBeTrue()->and($etat['derniere_reussite']->diffInHours(now(), true))->toBeGreaterThanOrEqual(2)
        ->and($etat['dernier_essai'])->not->toBeNull()->and($etat['dernier_echec']['message'])->toBe('REFUS (copier) : trop vieux')->and($etat['dernier_echec']['depasse'])->toBeTrue();
});

it('lit un état absent ou abîmé sans planter', function () {
    expect(stockage()->status())->toBe(['derniere_reussite' => null, 'dernier_essai' => null, 'dernier_echec' => null, 'fichier_present' => false]);

    file_put_contents($this->etat, "n'importe quoi\nderniere_reussite=pas-une-date\ndernier_echec=sans-date|message\n");
    $etat = stockage()->status();

    expect($etat['fichier_present'])->toBeTrue()->and($etat['derniere_reussite'])->toBeNull()->and($etat['dernier_echec'])->toBeNull();
});

it('présente un incident plus récent que la dernière réussite comme non dépassé', function () {
    file_put_contents($this->etat, 'derniere_reussite='.now()->subDays(2)->toIso8601String()."\ndernier_echec=".now()->subHour()->toIso8601String()."|ÉCHEC\n");

    expect(stockage()->status()['dernier_echec']['depasse'])->toBeFalse();
});

// ─── Écran ───────────────────────────────────────────────────────────────────

it('réserve l’écran au super administrateur', function () {
    $this->get(route('espace.parametres.copie'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('espace.parametres.copie'))->assertForbidden();
    $this->actingAs($this->mod)->get(route('espace.parametres.copie'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('espace.parametres.copie'))->assertOk()->assertSeeText('Copie hors serveur');
    $this->actingAs($this->admin)->get(route('espace.parametres'))->assertSee(route('espace.parametres.copie'), false);
});

it('dit franchement quand la copie n’est pas configurée', function () {
    Livewire::actingAs($this->admin)->test(ParametresCopie::class)
        ->assertSeeText('Non configurée : aucune sauvegarde n’existe hors du serveur.')->assertSeeText('Renseigner les accès')->assertSeeText('Enregistrer et tester la connexion')
        ->assertSeeText('Phrase de secours existante (facultative)')->assertDontSeeText('Réafficher la phrase de secours');
});

it('montre l’état de la copie, alerte quand aucune copie récente n’a réussi, et signale l’essai jamais fait', function () {
    stockage()->save($this->admin, acces());

    Livewire::actingAs($this->admin)->test(ParametresCopie::class)
        ->assertSeeText('Configurée : la copie se lance chaque nuit vers 4 h 30.')->assertSeeText('aucune pour le moment')->assertSeeText('Attention : aucune copie réussie depuis plus de 36 heures')
        ->assertSeeText('jamais fait');

    file_put_contents($this->etat, 'derniere_reussite='.now()->subHours(2)->toIso8601String()."\ndernier_essai=".now()->subHour()->toIso8601String()."\n");

    Livewire::actingAs($this->admin)->test(ParametresCopie::class)
        ->assertSeeText('Dernière copie réussie et vérifiée')->assertDontSeeText('Attention : aucune copie réussie')->assertSeeText('Dernier essai de restauration réussi')->assertDontSeeText('jamais fait');

    file_put_contents($this->etat, 'derniere_reussite='.now()->subHours(40)->toIso8601String()."\n");
    Livewire::actingAs($this->admin)->test(ParametresCopie::class)->assertSeeText('Attention : aucune copie réussie depuis plus de 36 heures');
});

it('affiche le dernier incident', function () {
    stockage()->save($this->admin, acces());
    file_put_contents($this->etat, 'derniere_reussite='.now()->subDays(3)->toIso8601String()."\ndernier_echec=".now()->subHour()->toIso8601String()."|REFUS (copier) : stockage injoignable\n");

    Livewire::actingAs($this->admin)->test(ParametresCopie::class)->assertSeeText('Dernier incident')->assertSeeText('REFUS (copier) : stockage injoignable')->assertDontSeeText('(depuis, une copie a réussi)');
});

it('enregistre depuis l’écran, montre la phrase une seule fois, et ne réaffiche jamais les clés', function () {
    $ecran = Livewire::actingAs($this->admin)->test(ParametresCopie::class)
        ->set('endpoint', 'https://s3.exemple.org')->set('bucket', 'gamad-copies')->set('accessKey', 'CLE-D-ACCES-1234')->set('secretKey', 'CLE-SECRETE-5678')
        ->call('save')->assertHasNoErrors()->assertSeeText('l’essai d’écriture et de relecture a réussi')->assertSeeText('Phrase de secours : à noter maintenant')
        ->assertSet('accessKey', '')->assertSet('secretKey', '');

    $phrase = $ecran->get('shownPassphrase');
    expect($phrase)->toMatch('/^[A-Za-z0-9]{4}(-[A-Za-z0-9]{4}){7}$/');
    $html = $ecran->html();
    expect($html)->not->toContain('CLE-D-ACCES-1234')->not->toContain('CLE-SECRETE-5678');

    $ecran->call('noted')->assertSet('shownPassphrase', null)->assertDontSeeText('Phrase de secours : à noter maintenant')->assertSeeText('Réafficher la phrase de secours');

    // Un nouvel affichage de la page ne remontre rien : ni phrase, ni clés.
    $neuf = Livewire::actingAs($this->admin)->test(ParametresCopie::class)->assertSet('shownPassphrase', null)->assertSet('endpoint', 'https://s3.exemple.org')->assertSet('bucket', 'gamad-copies');
    expect($neuf->html())->not->toContain($phrase)->not->toContain('CLE-D-ACCES-1234');
});

it('montre l’erreur du stockage et n’enregistre rien quand l’essai échoue', function () {
    $this->sonde->refus = 'Le stockage a refusé l’essai (AccessDenied).';

    Livewire::actingAs($this->admin)->test(ParametresCopie::class)
        ->set('endpoint', 'https://s3.exemple.org')->set('bucket', 'gamad-copies')->set('accessKey', 'a-a-a-a')->set('secretKey', 'b-b-b-b')
        ->call('save')->assertHasErrors('storage')->assertSeeText('AccessDenied')->assertSeeText('Rien n’a été enregistré')->assertSet('shownPassphrase', null);

    expect(stockage()->configured())->toBeFalse();
});

it('montre les erreurs de saisie sous chaque champ', function () {
    Livewire::actingAs($this->admin)->test(ParametresCopie::class)
        ->set('endpoint', 'http://pas-chiffre.exemple.org')->set('bucket', 'X')->set('accessKey', '')->set('secretKey', '')
        ->call('save')->assertHasErrors(['endpoint', 'bucket', 'access_key', 'secret_key'])->assertSeeText('doit commencer par https://');
});

it('réaffiche la phrase sur mot de passe depuis l’écran, avec trace au journal', function () {
    $phrase = stockage()->save($this->admin, acces());

    Livewire::actingAs($this->admin)->test(ParametresCopie::class)
        ->assertSeeText('Réafficher la phrase de secours')->call('askToReveal')->assertSeeText('Votre mot de passe')
        ->set('password', 'faux')->call('reveal')->assertHasErrors('password')->assertSet('shownPassphrase', null)
        ->set('password', 'motdepasse-solide')->call('reveal')->assertHasNoErrors()->assertSet('shownPassphrase', $phrase)->assertSeeText($phrase)
        ->call('noted')->assertSet('shownPassphrase', null);

    expect(AdminAction::query()->where('action', 'backup.passphrase_revealed')->count())->toBe(1);
});

it('refuse chaque acte de l’écran à un autre que le super administrateur', function () {
    $this->actingAs($this->mod);
    $ecran = new ParametresCopie;

    foreach (['save', 'askToReveal', 'reveal'] as $acte) {
        expect(fn () => $ecran->{$acte}(stockage()))->toThrow(AuthorizationException::class);
    }
    expect(fn () => $ecran->askToReveal())->toThrow(AuthorizationException::class);

    expect(stockage()->configured())->toBeFalse();
});

// ─── Journal ─────────────────────────────────────────────────────────────────

it('rend lisible l’affichage de la phrase au journal, pour le seul super administrateur', function () {
    stockage()->save($this->admin, acces());
    stockage()->revealPassphrase($this->admin, 'motdepasse-solide');

    Livewire::actingAs($this->admin)->test(JournalAdministration::class)->assertSeeText('a affiché la phrase de secours de la copie hors serveur');
    Livewire::actingAs($this->mod)->test(JournalAdministration::class)->assertDontSeeText('phrase de secours');
});
