<?php

namespace App\Http\Controllers;

use App\Enums\PublicationType;
use App\Models\Company;
use App\Models\Publication;
use App\Services\Publications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CompanyPostController extends Controller
{
    private function authorizeOwner(Company $company): void
    {
        $user = Auth::user();
        abort_unless($user?->fresh()->isActive() && $user->hasVerifiedEmail()
            && $company->fresh()?->verified() && $company->owner_id === $user->id, 403);
    }

    public function create(Company $company)
    {
        $this->authorizeOwner($company);

        return view('pages.company-post-create', [
            'company' => $company,
            'types' => [
                'news' => ['label' => 'Actualité', 'description' => 'Partager une information, une initiative ou un conseil.'],
                'work' => ['label' => 'Réalisation', 'description' => 'Montrer un travail réalisé par votre entreprise.'],
                'opportunity' => ['label' => 'Opportunité', 'description' => 'Rechercher une compétence ou proposer une collaboration.'],
            ],
        ]);
    }

    public function store(Request $request, Company $company, Publications $publications)
    {
        $this->authorizeOwner($company);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['news', 'work', 'opportunity'])],
            'body' => ['required', 'string', 'max:'.Publication::BODY_MAX],
            'media' => ['nullable', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf'],
        ]);
        $type = match ($data['kind']) {
            'work' => PublicationType::PersonalWork,
            'opportunity' => PublicationType::Need,
            default => PublicationType::Share,
        };

        $publications->publish(Auth::user(), $type, [
            'body' => $data['body'],
            'visibility' => Publication::PUBLIC,
            'media' => $request->file('media'),
        ], company: $company);

        return redirect()->route('entreprises.show', $company).'#publications';
    }
}
