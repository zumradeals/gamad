<?php

namespace App\Services\Publicite;

use App\Services\ProfilePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * L'image d'une annonce (D-212) : une seule, JPEG, PNG ou WebP de 3 Mo au plus, ré-encodée en JPEG (aucune métadonnée conservée) et
 * rangée sur le disque privé. Aucun nom ni type déclaré par le navigateur n'est repris. Elle n'est servie que par la page protégée.
 */
class AdImages
{
    public function store(mixed $file): string
    {
        Validator::make(['image' => $file], ['image' => ['required', 'file', 'max:3072', 'mimetypes:image/jpeg,image/png,image/webp']], [
            'image.max' => 'L’image ne peut pas dépasser 3 Mo.', 'image.mimetypes' => 'Choisissez une image JPEG, PNG ou WebP.',
        ])->validate();
        /** @var UploadedFile $file */
        try {
            $bytes = ProfilePhoto::encodePublication((string) file_get_contents($file->getRealPath()));
        } catch (\InvalidArgumentException $error) {
            throw ValidationException::withMessages(['image' => $error->getMessage()]);
        }
        $path = 'ad-images/'.Str::uuid().'.jpg';
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw ValidationException::withMessages(['image' => 'L’image n’a pas pu être enregistrée. Réessayez.']);
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && str_starts_with($path, 'ad-images/')) {
            Storage::disk('local')->delete($path);
        }
    }
}
