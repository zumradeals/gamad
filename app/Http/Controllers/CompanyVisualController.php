<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CompanyVisualController extends Controller
{
    /** Ne jamais exposer directement le disque privé : droits contrôlés à chaque affichage. */
    public function __invoke(Request $request, Company $company, string $kind): Response
    {
        abort_unless(in_array($kind, ['logo', 'banner'], true) || preg_match('/^service-\d$/', $kind), 404);
        $viewer = $request->user();
        abort_unless($company->verified() || ($viewer?->isActive()
            && ($viewer->id === $company->owner_id || $viewer->isSuperAdmin())), 404);

        $path = str_starts_with($kind, 'service-')
            ? ($company->services[(int) substr($kind, 8)]['image_path'] ?? null)
            : $company->{$kind.'_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $response = Storage::disk('local')->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setEtag(md5($path));
        $response->headers->set('Cache-Control', 'private, no-cache');
        $response->isNotModified($request);

        return $response;
    }
}
