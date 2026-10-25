<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyBranding
{
    /** L'identité administrative vérifiée (raison sociale, RCCM, pays) ne se modifie pas ici. */
    public function update(User $actor, Company $company, array $input): Company
    {
        abort_unless($actor->fresh()->isActive() && $actor->hasVerifiedEmail()
            && $company->owner_id === $actor->id, 403);

        $data = Validator::make($input, [
            'summary' => ['nullable', 'string', 'max:280'],
            'description' => ['nullable', 'string', 'max:1800'],
            'city' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
            'banner' => ['nullable', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
            'services' => ['nullable', 'array', 'max:'.Company::MAX_SERVICES],
            'services.*.name' => ['nullable', 'string', 'max:80'],
            'services.*.category' => ['nullable', 'string', 'max:40'],
            'services.*.description' => ['nullable', 'string', 'max:200'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_banner' => ['nullable', 'boolean'],
        ])->validate();

        if ((! empty($data['remove_logo']) && ! empty($data['logo']))
            || (! empty($data['remove_banner']) && ! empty($data['banner']))) {
            throw ValidationException::withMessages(['logo' => 'Pour chaque image, choisissez soit de la remplacer, soit de la retirer.']);
        }

        $created = [];
        try {
            $updated = DB::transaction(function () use ($actor, $company, $data, &$created) {
                $row = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
                abort_unless($row->owner_id === $actor->id, 403);

                foreach (['summary', 'description', 'city', 'website', 'email'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $row->{$field} = trim((string) ($data[$field] ?? '')) ?: null;
                    }
                }

                // Vitrine : seules les fiches nommées sont conservées ; la clé absente laisse la vitrine inchangée.
                if (array_key_exists('services', $data)) {
                    $row->services = collect($data['services'] ?? [])
                        ->map(fn ($item) => [
                            'name' => Normalizer::display((string) ($item['name'] ?? '')),
                            'category' => Normalizer::display((string) ($item['category'] ?? '')),
                            'description' => Normalizer::display((string) ($item['description'] ?? '')),
                        ])
                        ->filter(fn ($item) => $item['name'] !== '')->values()->all() ?: null;
                }

                foreach (['logo', 'banner'] as $kind) {
                    if (! empty($data[$kind])) {
                        $bytes = $this->encode($data[$kind], $kind);
                        $path = 'company-branding/'.Str::uuid().'.jpg';
                        if (! Storage::disk('local')->put($path, $bytes)) {
                            throw ValidationException::withMessages([$kind => 'L’image n’a pas pu être enregistrée.']);
                        }
                        $created[] = $path;
                        $row->{$kind.'_path'} = $path;
                    } elseif (! empty($data['remove_'.$kind])) {
                        $row->{$kind.'_path'} = null;
                    }
                }

                $row->save();

                return $row;
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($created);
            throw $error;
        }

        // Supprimer les anciennes versions après la réussite en base, pas avant.
        foreach (['logo_path', 'banner_path'] as $field) {
            if ($company->{$field} && $company->{$field} !== $updated->{$field}) {
                Storage::disk('local')->delete($company->{$field});
            }
        }

        return $updated;
    }

    private function encode(UploadedFile $file, string $kind): string
    {
        if (! ProfilePhoto::available()) {
            throw ValidationException::withMessages([$kind => 'Le traitement des images n’est pas disponible sur ce serveur.']);
        }

        try {
            $raw = file_get_contents($file->getRealPath());
            // Le processeur existant valide 25 mégapixels max, redresse les JPEG et efface les métadonnées EXIF/GPS.
            $sanitized = $kind === 'logo' ? ProfilePhoto::encode($raw) : ProfilePhoto::encodePublication($raw);
        } catch (\InvalidArgumentException $error) {
            throw ValidationException::withMessages([$kind => $error->getMessage()]);
        }

        if ($kind === 'logo') {
            return $sanitized; // Cadrage carré centré, JPEG 512 px au maximum.
        }

        $source = @imagecreatefromstring($sanitized);
        if (! $source) {
            throw ValidationException::withMessages(['banner' => 'La bannière n’a pas pu être traitée.']);
        }

        $w = imagesx($source);
        $h = imagesy($source);
        // Format 8:3 ; sur mobile, l'image sera rognée visuellement via object-cover.
        $targetW = 1600;
        $targetH = 600;
        if ($w * $targetH > $h * $targetW) {
            $cropH = $h;
            $cropW = max(1, (int) floor($h * $targetW / $targetH));
        } else {
            $cropW = $w;
            $cropH = max(1, (int) floor($w * $targetH / $targetW));
        }
        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0,
            (int) floor(($w - $cropW) / 2), (int) floor(($h - $cropH) / 2),
            $targetW, $targetH, $cropW, $cropH);
        ob_start();
        imagejpeg($canvas, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        if ($bytes === '') {
            throw ValidationException::withMessages(['banner' => 'La bannière n’a pas pu être enregistrée.']);
        }

        return $bytes;
    }
}
