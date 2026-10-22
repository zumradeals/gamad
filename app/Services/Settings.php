<?php

namespace App\Services;

use App\Models\User;
use App\Settings\Definition;
use App\Settings\Registry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Réglages métier enregistrés en base (D-049) : lecture, modification journalisée et application à la
 * configuration de Laravel au démarrage.
 *
 * Les valeurs secrètes sont chiffrées avec la clé de l'application et ne sont jamais renvoyées par `values()` :
 * seul `secret()` les déchiffre, pour l'application à la configuration.
 */
class Settings
{
    /** @var array<string, object{value: ?string, is_secret: bool}>|null */
    private ?array $rows = null;

    /**
     * Valeur d'un réglage non secret, avec sa valeur par défaut et son type.
     */
    public function get(string $key): mixed
    {
        $definition = Registry::find($key);

        if ($definition->isSecret()) {
            throw new \LogicException("Le réglage secret « $key » ne se lit pas avec get().");
        }

        $row = $this->rows()[$key] ?? null;

        return $row === null ? $definition->default : $this->cast($definition, $row->value);
    }

    /**
     * Toutes les valeurs non secrètes, par clé.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $values = [];

        foreach (Registry::all() as $key => $definition) {
            if (! $definition->isSecret()) {
                $values[$key] = $this->get($key);
            }
        }

        return $values;
    }

    public function hasSecret(string $key): bool
    {
        $this->assertSecret($key);

        return filled(($this->rows()[$key] ?? null)?->value);
    }

    public function secret(string $key): ?string
    {
        $this->assertSecret($key);

        $value = ($this->rows()[$key] ?? null)?->value;

        return filled($value) ? Crypt::decryptString($value) : null;
    }

    /**
     * Enregistre les changements et inscrit chacun au journal d'administration : ancienne et nouvelle valeur,
     * sauf pour les secrets (seulement « modifié »). Retourne les clés réellement modifiées.
     *
     * @param  array<string, mixed>  $changes  clé => nouvelle valeur ; null efface. Une valeur identique à la valeur actuelle est ignorée.
     * @return array<int, string>
     */
    public function update(array $changes, User $actor): array
    {
        $modified = [];

        DB::transaction(function () use ($changes, $actor, &$modified) {
            foreach ($changes as $key => $new) {
                $definition = Registry::find($key);
                $new = $this->normalize($definition, $new);

                if ($definition->isSecret()) {
                    // Pour un secret, « inchangé » est décidé par l'appelant (champ vide) : on remplace toujours.
                    $this->store($definition, $new === null ? null : Crypt::encryptString($new));
                    AdminJournal::record($actor, 'setting.changed', reason: null, payload: ['secret' => true, 'etat' => 'modifié'], subjectType: 'setting', subjectId: $key);
                    $modified[] = $key;

                    continue;
                }

                $old = $this->get($key);
                // Valeur effective : effacer un champ qui a une valeur par défaut revient à cette valeur.
                $effective = $new ?? $definition->default;

                if ($old === $effective) {
                    continue;
                }

                $this->store($definition, $new === null ? null : (string) $new);
                AdminJournal::record($actor, 'setting.changed', reason: null, payload: ['ancien' => $old, 'nouveau' => $effective], subjectType: 'setting', subjectId: $key);
                $modified[] = $key;
            }
        });

        $this->rows = null;

        return $modified;
    }

    /**
     * Applique les réglages enregistrés à la configuration de Laravel. Appelé au démarrage de chaque requête ;
     * ne fait rien (et ne plante jamais) tant que la table n'existe pas ou que la base est injoignable.
     */
    public function applyToConfig(): void
    {
        try {
            $this->rows = null;
            $this->rows();
        } catch (Throwable) {
            return;
        }

        config([
            'app.name' => $this->get('app_name'),
            'gamad.limits.register_per_hour' => $this->get('limit_register_per_hour'),
            'gamad.limits.login_per_minute' => $this->get('limit_login_per_minute'),
        ]);

        if (filled($url = $this->get('app_url'))) {
            config(['app.url' => $url]);
        }

        $this->applyMailConfig();
    }

    private function applyMailConfig(): void
    {
        if (blank($host = $this->get('mail_host'))) {
            return;
        }

        $encryption = $this->get('mail_encryption');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => array_filter([
                'transport' => 'smtp',
                'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                'host' => $host,
                'port' => $this->get('mail_port'),
                'username' => $this->get('mail_username'),
                'password' => $this->secret('mail_password'),
                'timeout' => 15,
                // STARTTLS obligatoire pour « starttls » ; aucun chiffrement opportuniste pour « none ».
                'require_tls' => $encryption === 'starttls' ? true : null,
                'auto_tls' => $encryption === 'none' ? false : null,
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);

        if (filled($address = $this->get('mail_from_address'))) {
            config([
                'mail.from.address' => $address,
                'mail.from.name' => $this->get('mail_from_name') ?: $this->get('app_name'),
            ]);
        }
    }

    /**
     * @return array<string, object{value: ?string, is_secret: bool}>
     */
    private function rows(): array
    {
        return $this->rows ??= DB::table('settings')->get(['key', 'value', 'is_secret'])->keyBy('key')->all();
    }

    private function store(Definition $definition, ?string $value): void
    {
        DB::table('settings')->upsert(
            [['key' => $definition->key, 'value' => $value, 'is_secret' => $definition->isSecret(), 'updated_at' => Carbon::now()]],
            ['key'],
            ['value', 'is_secret', 'updated_at'],
        );
    }

    private function normalize(Definition $definition, mixed $value): string|int|null
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return $definition->type === Definition::INTEGER ? (int) $value : (string) $value;
    }

    private function cast(Definition $definition, ?string $stored): mixed
    {
        if ($stored === null || $stored === '') {
            return $definition->default;
        }

        return $definition->type === Definition::INTEGER ? (int) $stored : $stored;
    }

    private function assertSecret(string $key): void
    {
        if (! Registry::find($key)->isSecret()) {
            throw new \LogicException("Le réglage « $key » n'est pas un secret.");
        }
    }
}
