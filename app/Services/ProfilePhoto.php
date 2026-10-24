<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Traitement de la photo de profil (D-070) : JPEG, PNG ou WebP, recadrée en carré au centre, redessinée de zéro en
 * JPEG. Rien de l'image d'origine n'est conservé hors des pixels : ni EXIF (dont la position GPS), ni XMP, ni profil
 * de couleur, ni commentaire. L'orientation EXIF est appliquée avant d'être oubliée, pour que la photo reste droite.
 */
class ProfilePhoto
{
    public const SIDE = 512;

    public const QUALITY = 85;

    /** 25 mégapixels : au-delà, le décodage risquerait d'épuiser la mémoire du serveur. */
    public const MAX_PIXELS = 25_000_000;

    private const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /** Sans l'extension GD du serveur, la photo n'est pas proposée (rien de simulé). */
    public static function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagejpeg') && function_exists('imagecopyresampled');
    }

    /**
     * @return string octets du JPEG carré
     *
     * @throws InvalidArgumentException message en français, affichable tel quel
     */
    public static function encodePublication(string $raw): string
    {
        if (! self::available()) {
            throw new InvalidArgumentException('L’envoi d’images n’est pas disponible sur ce serveur.');
        }
        $info = @getimagesizefromstring($raw);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > 25000000) {
            throw new InvalidArgumentException('Choisissez une image JPEG, PNG ou WebP de 25 millions de pixels au plus.');
        }
        $source = @imagecreatefromstring($raw);
        if (! $source) {
            throw new InvalidArgumentException('Cette image ne peut pas être lue.');
        }
        if ($info[2] === IMAGETYPE_JPEG) {
            $source = self::oriented($source, self::orientation($raw));
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, 2048 / max($width, $height));
        $target = imagecreatetruecolor(max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)));
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
        ob_start();
        imagejpeg($target, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);
        if (! $jpeg) {
            throw new InvalidArgumentException('Cette image ne peut pas être enregistrée.');
        }

        return $jpeg;
    }

    public static function encode(string $data): string
    {
        $info = @getimagesizefromstring($data);

        if ($info === false || ! in_array($info[2], self::TYPES, true)) {
            throw new InvalidArgumentException('Ce fichier n’est pas une photo JPEG, PNG ou WebP.');
        }

        if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('Cette photo est trop grande (25 mégapixels au plus). Réduisez-la puis réessayez.');
        }

        $source = @imagecreatefromstring($data);

        if ($source === false) {
            throw new InvalidArgumentException('Cette photo n’a pas pu être lue. Essayez un autre fichier.');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $source = self::oriented($source, self::orientation($data));
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $target = min($side, self::SIDE);

        $canvas = imagecreatetruecolor($target, $target);
        // Fond blanc : une photo PNG ou WebP transparente ne devient pas noire en JPEG.
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $target, $target, $side, $side);

        ob_start();
        imagejpeg($canvas, null, self::QUALITY);
        $jpeg = (string) ob_get_clean();

        return $jpeg;
    }

    private static function orientation(string $data): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($data));

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }

    /** Remet la photo droite selon l'orientation EXIF (1 à 8), puis l'orientation est oubliée avec le reste. */
    private static function oriented(\GdImage $image, int $orientation): \GdImage
    {
        if (in_array($orientation, [2, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        if (in_array($orientation, [4, 5], true)) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        $angle = match ($orientation) {
            3 => 180,
            5, 6, 7 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
