<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanyDocumentController extends Controller
{
    public function __invoke(Company $company, CompanyDocument $document): BinaryFileResponse
    {
        abort_unless(auth()->user()?->isActive() && auth()->user()->isSuperAdmin(), 403);
        abort_unless($document->company_id === $company->id, 404);
        abort_unless(Storage::disk('local')->exists($document->private_path), 404);

        $response = Storage::disk('local')->download(
            $document->private_path,
            'justificatif-'.$document->kind.'.'.$document->extension,
        );
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
