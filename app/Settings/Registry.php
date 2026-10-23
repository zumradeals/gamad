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

    public const GROUP_TEXTS = 'textes';

    public const GROUP_BACKUP = 'copie';

    public const GROUP_WALLET = 'portefeuille';

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

            // Charte de conduite et mentions légales (D-146) : rien n'est publié tant que le Président n'a pas adopté les textes.
            new Definition('legal_publisher', self::GROUP_TEXTS, 'Éditeur du site', Definition::STRING, null, ['nullable', 'string', 'max:255']),
            new Definition('legal_director', self::GROUP_TEXTS, 'Responsable de la publication', Definition::STRING, null, ['nullable', 'string', 'max:255']),
            new Definition('legal_contact', self::GROUP_TEXTS, 'Adresse e-mail de contact', Definition::STRING, null, ['nullable', 'email:rfc', 'max:255']),
            new Definition('legal_host', self::GROUP_TEXTS, 'Hébergeur', Definition::STRING, null, ['nullable', 'string', 'max:500']),
            new Definition('charter_adopted_at', self::GROUP_TEXTS, 'Charte adoptée le', Definition::STRING, null, ['nullable', 'date']),
            new Definition('legal_adopted_at', self::GROUP_TEXTS, 'Mentions légales adoptées le', Definition::STRING, null, ['nullable', 'date']),

            // Copie hors serveur (Lot 5c, D-156) : accès au stockage compatible S3 et phrase de secours, chiffrés en base.
            new Definition('backup_s3_endpoint', self::GROUP_BACKUP, 'Adresse du stockage', Definition::STRING, null, ['nullable', 'string', 'max:255']),
            new Definition('backup_s3_bucket', self::GROUP_BACKUP, 'Nom du bucket', Definition::STRING, null, ['nullable', 'string', 'max:63']),
            new Definition('backup_s3_region', self::GROUP_BACKUP, 'Région', Definition::STRING, null, ['nullable', 'string', 'max:60']),
            new Definition('backup_s3_access_key', self::GROUP_BACKUP, 'Clé d’accès', Definition::SECRET, null, ['nullable', 'string', 'max:255']),
            new Definition('backup_s3_secret_key', self::GROUP_BACKUP, 'Clé secrète', Definition::SECRET, null, ['nullable', 'string', 'max:255']),
            new Definition('backup_passphrase', self::GROUP_BACKUP, 'Phrase de secours', Definition::SECRET, null, ['nullable', 'string', 'max:255']),

            // Portefeuilles ZAHAB (Lot 6, D-164) : masqués tant que le Président ne les ouvre pas ; plafonds de solde en ZAHAB (D-172).
            new Definition('wallet_enabled', self::GROUP_WALLET, 'Portefeuilles', Definition::ENUM, '0', ['required', 'in:0,1'], [
                '0' => 'Fermés : personne ne les voit',
                '1' => 'Ouverts : chaque personne et chaque ZUMRA a son portefeuille',
            ]),
            new Definition('wallet_max_balance_person', self::GROUP_WALLET, 'Solde maximum d’une personne (ZAHAB)', Definition::INTEGER, 500000, ['required', 'integer', 'between:1,100000000']),
            new Definition('wallet_max_balance_zumra', self::GROUP_WALLET, 'Solde maximum d’une ZUMRA (ZAHAB)', Definition::INTEGER, 5000000, ['required', 'integer', 'between:1,1000000000']),
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
