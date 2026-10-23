<?php

namespace App\Services;

use App\Models\User;
use App\Services\Backup\StorageProbe;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Accès au stockage de la copie hors serveur, saisis par le Président dans Espace → Paramètres (D-155). Les clés et la phrase de
 * secours sont des réglages secrets : chiffrés en base avec la clé de l'application, jamais renvoyés à l'écran, et chaque
 * modification est inscrite au journal d'administration. La copie nocturne (deploy/copie-hors-serveur.sh) relit ces réglages par la
 * commande gamad:copie-configuration.
 *
 * La phrase de secours est la seule clé des copies : générée une fois, montrée une fois (ou réaffichée sur mot de passe, avec trace
 * au journal), elle ne change plus sans décision.
 */
class BackupStorage
{
    public const PASSPHRASE_MIN = 16;

    /** Âge à partir duquel une copie réussie n'est plus « récente » (la copie est nocturne). */
    public const STALE_AFTER_HOURS = 36;

    public function __construct(private readonly Settings $settings, private readonly StorageProbe $probe) {}

    public function configured(): bool
    {
        return filled($this->settings->get('backup_s3_endpoint'))
            && filled($this->settings->get('backup_s3_bucket'))
            && $this->settings->hasSecret('backup_s3_access_key')
            && $this->settings->hasSecret('backup_s3_secret_key')
            && $this->settings->hasSecret('backup_passphrase');
    }

    /** @return array{endpoint: ?string, bucket: ?string, region: ?string} Ce qui peut s'afficher ; jamais une clé. */
    public function visible(): array
    {
        return [
            'endpoint' => $this->settings->get('backup_s3_endpoint'),
            'bucket' => $this->settings->get('backup_s3_bucket'),
            'region' => $this->settings->get('backup_s3_region'),
        ];
    }

    public function hasPassphrase(): bool
    {
        return $this->settings->hasSecret('backup_passphrase');
    }

    /**
     * Valide les accès, les essaie pour de bon (écriture, relecture, effacement à travers le chiffrement), et ne les enregistre
     * que si l'essai réussit. Retourne la phrase de secours si elle vient d'être créée (à montrer une seule fois), sinon null.
     *
     * @param  array{endpoint?: ?string, bucket?: ?string, region?: ?string, access_key?: ?string, secret_key?: ?string, passphrase?: ?string}  $input
     *
     * @throws ValidationException
     */
    public function save(User $actor, array $input): ?string
    {
        Gate::forUser($actor)->authorize('super-admin');

        $endpoint = Str::of((string) ($input['endpoint'] ?? ''))->trim()->rtrim('/')->toString();
        $bucket = trim((string) ($input['bucket'] ?? ''));
        $region = trim((string) ($input['region'] ?? ''));
        // Champ vide = clé inchangée, comme pour le mot de passe SMTP.
        $access = trim((string) ($input['access_key'] ?? '')) ?: $this->settings->secret('backup_s3_access_key');
        $secret = trim((string) ($input['secret_key'] ?? '')) ?: $this->settings->secret('backup_s3_secret_key');
        $provided = trim((string) ($input['passphrase'] ?? ''));

        $errors = [];

        if (! preg_match('#^https://[^\s/]+#', $endpoint) && ! preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?$#', $endpoint)) {
            $errors['endpoint'] = 'L’adresse du stockage doit commencer par https:// : une copie ne doit pas voyager sans connexion chiffrée.';
        }
        if (! preg_match('/^[a-z0-9][a-z0-9.-]{1,62}$/', $bucket)) {
            $errors['bucket'] = 'Le nom du bucket n’est pas valide (lettres minuscules, chiffres, points et tirets, de 2 à 63 caractères).';
        }
        if (blank($access) || preg_match('/\s/', (string) $access)) {
            $errors['access_key'] = blank($access) ? 'Indiquez la clé d’accès.' : 'La clé d’accès ne doit contenir aucun espace.';
        }
        if (blank($secret) || preg_match('/\s/', (string) $secret)) {
            $errors['secret_key'] = blank($secret) ? 'Indiquez la clé secrète.' : 'La clé secrète ne doit contenir aucun espace.';
        }
        if ($region !== '' && ! preg_match('/^[A-Za-z0-9-]{1,60}$/', $region)) {
            $errors['region'] = 'La région n’est pas valide.';
        }

        $existing = $this->settings->secret('backup_passphrase');
        if ($existing !== null && $provided !== '') {
            $errors['passphrase'] = 'Une phrase de secours existe déjà : elle ne se change pas ici, car les copies déjà faites deviendraient illisibles.';
        } elseif ($provided !== '' && mb_strlen($provided) < self::PASSPHRASE_MIN) {
            $errors['passphrase'] = 'La phrase de secours est trop courte ('.self::PASSPHRASE_MIN.' caractères au moins).';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $generated = null;
        $passphrase = $existing;
        if ($passphrase === null) {
            $passphrase = $provided !== '' ? $provided : ($generated = self::generatePassphrase());
        }

        $values = ['endpoint' => $endpoint, 'bucket' => $bucket, 'region' => $region, 'access_key' => $access, 'secret_key' => $secret, 'passphrase' => $passphrase];

        if (($refus = $this->probe->verify($this->render($values))) !== null) {
            throw ValidationException::withMessages(['storage' => $refus.' Rien n’a été enregistré : vérifiez l’adresse, le nom du bucket et les clés.']);
        }

        $this->settings->update([
            'backup_s3_endpoint' => $endpoint,
            'backup_s3_bucket' => $bucket,
            'backup_s3_region' => $region,
            'backup_s3_access_key' => $access,
            'backup_s3_secret_key' => $secret,
            'backup_passphrase' => $passphrase,
        ], $actor);

        return $generated;
    }

    /**
     * Réaffiche la phrase de secours, sur confirmation du mot de passe du Président. L'affichage est inscrit au journal.
     *
     * @throws ValidationException
     */
    public function revealPassphrase(User $actor, ?string $password): string
    {
        Gate::forUser($actor)->authorize('super-admin');

        if (blank($password) || ! Hash::check($password, $actor->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => 'Mot de passe incorrect.']);
        }

        $passphrase = $this->settings->secret('backup_passphrase');

        if ($passphrase === null) {
            throw ValidationException::withMessages(['password' => 'Aucune phrase de secours n’est enregistrée.']);
        }

        AdminJournal::record($actor, 'backup.passphrase_revealed');

        return $passphrase;
    }

    /**
     * Configuration rclone de la copie (stockage S3 et chiffrement), pour la commande gamad:copie-configuration.
     *
     * @throws RuntimeException si la copie n'est pas configurée
     */
    public function rcloneConfig(): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('La copie hors serveur n’est pas configurée.');
        }

        return $this->render([
            'endpoint' => (string) $this->settings->get('backup_s3_endpoint'),
            'bucket' => (string) $this->settings->get('backup_s3_bucket'),
            'region' => (string) $this->settings->get('backup_s3_region'),
            'access_key' => (string) $this->settings->secret('backup_s3_access_key'),
            'secret_key' => (string) $this->settings->secret('backup_s3_secret_key'),
            'passphrase' => (string) $this->settings->secret('backup_passphrase'),
        ]);
    }

    /**
     * État écrit par le script de copie (deploy/copie-hors-serveur.sh) : une ligne « clé=valeur » par information.
     *
     * @return array{derniere_reussite: ?Carbon, dernier_essai: ?Carbon, dernier_echec: ?array{quand: Carbon, message: string, depasse: bool}, fichier_present: bool}
     */
    public function status(): array
    {
        $path = (string) config('gamad.backup.state_file');
        $state = ['derniere_reussite' => null, 'dernier_essai' => null, 'dernier_echec' => null, 'fichier_present' => is_readable($path)];

        if (! $state['fichier_present']) {
            return $state;
        }

        $lines = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $lines[trim($key)] = trim($value);
        }

        $date = fn (?string $value) => filled($value) ? rescue(fn () => Carbon::parse($value), null, false) : null;

        $state['derniere_reussite'] = $date($lines['derniere_reussite'] ?? null);
        $state['dernier_essai'] = $date($lines['dernier_essai'] ?? null);

        if (filled($lines['dernier_echec'] ?? null)) {
            [$when, $message] = array_pad(explode('|', $lines['dernier_echec'], 2), 2, '');
            $quand = $date($when);
            $state['dernier_echec'] = $quand ? ['quand' => $quand, 'message' => $message, 'depasse' => $state['derniere_reussite'] !== null && $state['derniere_reussite']->gte($quand)] : null;
        }

        return $state;
    }

    public static function generatePassphrase(): string
    {
        // 32 caractères alphanumériques (environ 190 bits), par groupes de 4 pour être recopiés sans erreur.
        return implode('-', str_split(Str::random(32), 4));
    }

    /** @param  array{endpoint: string, bucket: string, region: string, access_key: string, secret_key: string, passphrase: string}  $v */
    private function render(array $v): string
    {
        $salt = substr(hash('sha256', 'gamad-sel:'.$v['passphrase']), 0, 40);

        $conf = "[gamad-s3]\ntype = s3\nprovider = Other\nenv_auth = false\n"
            ."access_key_id = {$v['access_key']}\nsecret_access_key = {$v['secret_key']}\nendpoint = {$v['endpoint']}\n";
        if ($v['region'] !== '') {
            $conf .= "region = {$v['region']}\n";
        }

        return $conf."\n[gamad-copie]\ntype = crypt\nremote = gamad-s3:{$v['bucket']}/gamad-reseau\n"
            ."filename_encryption = standard\ndirectory_name_encryption = true\n"
            .'password = '.$this->obscure($v['passphrase'])."\npassword2 = ".$this->obscure($salt)."\n";
    }

    /** Masquage au sens de rclone (réversible : ce n'est pas du chiffrement, le fichier reste protégé par ses droits). */
    private function obscure(string $value): string
    {
        $result = Process::input($value)->run(['rclone', 'obscure', '-']);

        if ($result->failed()) {
            throw new RuntimeException('rclone est introuvable ou a échoué : impossible de préparer la configuration de la copie.');
        }

        return trim($result->output());
    }
}
