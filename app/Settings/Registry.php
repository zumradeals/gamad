<?php

namespace App\Settings;

/**
 * Liste fermée des réglages métier (D-049). Aucune autre clé ne peut être enregistrée.
 */
final class Registry
{
    public const GROUP_MAIL = 'courriel';

    public const GROUP_PLATFORM = 'plateforme';

    public const GROUP_SECURITY = 'securite';

    /** @var array<string, Definition>|null */
    private static ?array $definitions = null;

    /**
     * @return array<string, Definition>
     */
    public static function all(): array
    {
        return self::$definitions ??= collect([
            new Definition('mail_host', self::GROUP_MAIL, 'Serveur SMTP', Definition::STRING, null, ['nullable', 'string', 'max:255']),
            new Definition('mail_port', self::GROUP_MAIL, 'Port', Definition::INTEGER, 587, ['nullable', 'integer', 'between:1,65535']),
            new Definition('mail_encryption', self::GROUP_MAIL, 'Chiffrement', Definition::ENUM, 'starttls', ['nullable', 'in:starttls,ssl,none'], [
                'starttls' => 'STARTTLS (en général port 587)',
                'ssl' => 'SSL/TLS (en général port 465)',
                'none' => 'Aucun (déconseillé)',
            ]),
            new Definition('mail_username', self::GROUP_MAIL, 'Identifiant', Definition::STRING, null, ['nullable', 'string', 'max:255']),
            new Definition('mail_password', self::GROUP_MAIL, 'Mot de passe', Definition::SECRET, null, ['nullable', 'string', 'max:255']),
            new Definition('mail_from_address', self::GROUP_MAIL, 'Adresse de l’expéditeur', Definition::STRING, null, ['nullable', 'email:rfc', 'max:255']),
            new Definition('mail_from_name', self::GROUP_MAIL, 'Nom de l’expéditeur', Definition::STRING, null, ['nullable', 'string', 'max:100']),

            new Definition('app_name', self::GROUP_PLATFORM, 'Nom de la plateforme', Definition::STRING, 'GAMAD', ['required', 'string', 'max:60']),
            new Definition('app_url', self::GROUP_PLATFORM, 'Adresse publique', Definition::STRING, null, ['nullable', 'url:https', 'max:255']),

            new Definition('limit_register_per_hour', self::GROUP_SECURITY, 'Inscriptions par heure et par adresse IP', Definition::INTEGER, 20, ['required', 'integer', 'between:1,1000']),
            new Definition('limit_login_per_minute', self::GROUP_SECURITY, 'Tentatives de connexion par minute', Definition::INTEGER, 5, ['required', 'integer', 'between:1,60']),
        ])->keyBy(fn (Definition $definition) => $definition->key)->all();
    }

    public static function find(string $key): Definition
    {
        return self::all()[$key] ?? throw new \InvalidArgumentException("Réglage inconnu : $key");
    }

    /**
     * @return array<string, Definition>
     */
    public static function group(string $group): array
    {
        return array_filter(self::all(), fn (Definition $definition) => $definition->group === $group);
    }
}
