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

    /**
     * Texte libre sur plusieurs lignes (présentation, publication, message) : retours à la ligne unifiés, caractères invisibles
     * retirés, espaces superflus réduits, jamais plus d'une ligne vide d'affilée. Le texte reste un texte brut : il n'est jamais
     * interprété (ni HTML, ni lien automatique).
     */
    public static function multiline(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[^\P{Cc}\n]|\p{Cf}/u', '', $value) ?? $value;
        $value = preg_replace('/[^\S\n]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/ ?\n ?/', "\n", $value) ?? $value;

        return trim(preg_replace("/\n{3,}/", "\n\n", $value) ?? $value);
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
