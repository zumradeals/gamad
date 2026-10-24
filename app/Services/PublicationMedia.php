<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Un fichier privé par publication ; aucun chemin ou type déclaré par le navigateur n’est accepté. */
class PublicationMedia
{
    public function store(mixed $file): array
    {
        if ($file === null) {
            return [];
        }
        Validator::make(['media' => $file], ['media' => ['required', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf']])->validate();
        /** @var UploadedFile $file */
        $mime = $file->getMimeType();
        $kind = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : 'document');
        if ($kind !== 'video' && $file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['media' => 'Une image ou un PDF ne peut pas dépasser 5 Mo.']);
        }
        $bytes = file_get_contents($file->getRealPath());
        if ($kind === 'image') {
            try {
                $bytes = ProfilePhoto::encodePublication($bytes);
            } catch (\InvalidArgumentException $error) {
                throw ValidationException::withMessages(['media' => $error->getMessage()]);
            }
            $mime = 'image/jpeg';
        }
        $extension = ['image/jpeg' => 'jpg', 'video/mp4' => 'mp4', 'video/webm' => 'webm', 'application/pdf' => 'pdf'][$mime];
        $path = 'publication-media/'.Str::uuid().'.'.$extension;
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw ValidationException::withMessages(['media' => 'Le fichier n’a pas pu être enregistré. Réessayez.']);
        }
        // Le nom original n’est jamais utilisé comme chemin, ni injecté dans un en-tête HTTP.
        $name = $kind === 'document' ? 'Document.pdf' : ($kind === 'video' ? 'Video.'.$extension : 'Image.jpg');

        return ['media_path' => $path, 'media_kind' => $kind, 'media_mime' => $mime, 'media_name' => $name];
    }
}
