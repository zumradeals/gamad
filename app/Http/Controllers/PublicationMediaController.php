<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PublicationMediaController extends Controller
{
    public function __invoke(Publication $publication)
    {
        Gate::authorize('view', $publication);
        abort_unless($publication->media_path && Storage::disk('local')->exists($publication->media_path), 404);
        $response = response()->file(Storage::disk('local')->path($publication->media_path), [
            'Content-Type' => $publication->media_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');
        if ($publication->media_kind === 'document') {
            $response->setContentDisposition('attachment', 'Document.pdf');
        }

        return $response;
    }
}
