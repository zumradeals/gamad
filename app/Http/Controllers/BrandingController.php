<?php

namespace App\Http\Controllers;

use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrandingController extends Controller
{
    public function edit()
    {
        Gate::authorize('super-admin');

        return view('pages.branding');
    }

    public function save(Request $request, Settings $settings)
    {
        Gate::authorize('super-admin');
        $request->validate(['kind' => ['required', 'in:logo,icon'], 'image' => ['required_without:remove', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'], 'remove' => ['sometimes', 'accepted']]);
        $key = 'brand_'.$request->input('kind');
        if ($request->boolean('remove')) {
            $settings->update([$key => null], $request->user());
        } else {
            if (! function_exists('imagecreatefromstring')) {
                throw ValidationException::withMessages(['image' => 'Le traitement d’images est indisponible sur ce serveur.']);
            }
            $source = @imagecreatefromstring($request->file('image')->get());
            if (! $source) {
                throw ValidationException::withMessages(['image' => 'Cette image ne peut pas être lue.']);
            }
            $width = imagesx($source);
            $height = imagesy($source);
            if ($request->input('kind') === 'icon' && $width !== $height) {
                imagedestroy($source);
                throw ValidationException::withMessages(['image' => 'Choisissez une image carrée pour l’icône.']);
            }
            $ratio = min(1, ($request->input('kind') === 'icon' ? 512 : 1024) / max($width, $height));
            $target = imagecreatetruecolor(max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)));
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
            imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
            ob_start();
            imagepng($target);
            $png = ob_get_clean();
            imagedestroy($source);
            imagedestroy($target);
            $path = 'publication-media/branding/'.Str::uuid().'.png';
            if (! $png || ! Storage::disk('local')->put($path, $png)) {
                throw ValidationException::withMessages(['image' => 'L’image n’a pas pu être enregistrée. Réessayez.']);
            }
            try {
                $settings->update([$key => $path], $request->user());
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($path);
                throw $e;
            }
        }

        return back()->with('status', 'Identité visuelle enregistrée.');
    }

    public function image(string $kind, Settings $settings)
    {
        abort_unless(in_array($kind, ['logo', 'icon'], true), 404);
        $path = $settings->get('brand_'.$kind);
        abort_unless(is_string($path) && preg_match('~^publication-media/branding/[a-f0-9-]{36}\.png$~D', $path) && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=0, must-revalidate']);
    }
}
