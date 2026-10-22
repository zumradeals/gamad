<?php

namespace App\Settings;

/**
 * Description d'un réglage métier modifiable dans la page « Paramètres ».
 */
final class Definition
{
    public const STRING = 'string';

    public const INTEGER = 'integer';

    public const ENUM = 'enum';

    public const SECRET = 'secret';

    /**
     * @param  array<int, string>  $rules  Règles de validation Laravel (sous forme de chaînes).
     * @param  array<string, string>  $options  Pour un réglage de type enum : valeur => libellé.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $group,
        public readonly string $label,
        public readonly string $type = self::STRING,
        public readonly mixed $default = null,
        public readonly array $rules = [],
        public readonly array $options = [],
    ) {}

    public function isSecret(): bool
    {
        return $this->type === self::SECRET;
    }
}
