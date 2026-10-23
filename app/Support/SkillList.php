<?php

namespace App\Support;

use App\Models\Skill;
use Illuminate\Validation\ValidationException;

/**
 * Liste de noms de capacités saisis par une personne (profil, publication) : nettoyés, sans doublon au sens de D-066,
 * validés (2 à 60 caractères, au moins une lettre ou un chiffre) et limités en nombre.
 */
final class SkillList
{
    /**
     * @param  array<int, mixed>  $names
     * @return array<int, string>
     *
     * @throws ValidationException
     */
    public static function clean(array $names, string $field, int $max, string $tooMany): array
    {
        $clean = [];

        foreach ($names as $name) {
            $display = Normalizer::display((string) $name);

            if ($display === '') {
                continue;
            }

            if (! self::isValid($display)) {
                throw ValidationException::withMessages([$field => self::invalidMessage()]);
            }

            $clean[Normalizer::key($display)] ??= $display;
        }

        if (count($clean) > $max) {
            throw ValidationException::withMessages([$field => $tooMany]);
        }

        return array_values($clean);
    }

    public static function isValid(string $display): bool
    {
        $length = mb_strlen($display);

        return $length >= Skill::MIN_LENGTH && $length <= Skill::MAX_LENGTH && preg_match('/[\p{L}\p{N}]/u', $display) === 1;
    }

    public static function invalidMessage(): string
    {
        return 'Chaque capacité comporte de '.Skill::MIN_LENGTH.' à '.Skill::MAX_LENGTH.' caractères, dont au moins une lettre ou un chiffre.';
    }
}
