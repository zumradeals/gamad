<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Forme normalisée d'un libellé (D-066) : sans tenir compte de la casse, des accents ni des espaces superflus.
 * « Comptabilité », « comptabilite » et « COMPTABILITÉ » donnent la même clé.
 */
final class Normalizer
{
    /** Libellé tel qu'on l'affiche : caractères invisibles retirés, espaces superflus réduits à un seul. */
    public static function display(string $value): string
    {
        $value = preg_replace('/[\p{Cc}\p{Cf}]/u', '', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /** Clé de comparaison et de recherche. */
    public static function key(string $value): string
    {
        $value = self::display($value);
        $key = mb_strtolower(Str::ascii($value));

        // Une écriture sans équivalent latin (autre alphabet, émoji) garde sa forme d'origine plutôt que de devenir vide.
        return trim($key, "? \t") === '' ? mb_strtolower($value) : $key;
    }

    /** Motif « contient » sûr pour LIKE : les caractères spéciaux de la saisie sont neutralisés. */
    public static function likeContains(string $key): string
    {
        return '%'.addcslashes($key, '\\%_').'%';
    }
}
