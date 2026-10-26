<?php

namespace App\Http\Controllers;

use App\Enums\PublicationType;
use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\ProjectCompanyLink;
use App\Models\Publication;
use App\Support\ZumraModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function show(Company $company)
    {
        $viewer = Auth::user();
        abort_unless($company->verified() || ($viewer?->isActive() && ($viewer->id === $company->owner_id || $viewer->isSuperAdmin())), 404);

        $verified = $company->verified();
        // Mêmes règles de visibilité et de modération que le Fil : une publication masquée n'apparaît jamais ici.
        $posts = fn () => Publication::query()->visibleTo($viewer)->where('company_id', $company->id)->whereNull('hidden_at');
        $works = $verified ? $posts()->where('type', PublicationType::PersonalWork->value)->with(['skills', 'domain', 'city'])->latest()->limit(12)->get() : collect();
        $offers = $verified ? $company->opportunities()->with(['city'])->latest('published_at')->limit(12)->get() : collect();

        // Projets portés ou accompagnés (P1) : liens confirmés par l'entreprise, projets dont le porteur a publié la présentation, module ouvert.
        $projectLinks = $verified && ZumraModule::accessibleTo($viewer)
            ? ProjectCompanyLink::query()->where('company_id', $company->id)->where('state', ProjectCompanyLink::CONFIRMED)
                ->whereHas('project', fn ($project) => $project->showcased())->with('project')->latest('decided_at')->limit(12)->get()
            : collect();

        return view('pages.entreprise', [
            'company' => $company,
            'projectLinks' => $projectLinks,
            'isOwner' => $viewer?->id === $company->owner_id,
            'following' => $viewer && $verified && DB::table('company_follows')
                ->where('user_id', $viewer->id)->where('company_id', $company->id)->exists(),
            'companyPosts' => $verified
                ? $posts()->with(['author.profile', 'company', 'skills', 'city', 'domain'])->latest()->limit(15)->get()
                : collect(),
            'works' => $works,
            'offers' => $offers,
            'services' => collect($company->services ?? [])->filter(fn ($item) => filled($item['name'] ?? null))->values(),
            // Statistiques réelles, affichées seulement lorsqu'elles ne sont pas nulles.
            'stats' => $verified ? array_filter([
                'Publications' => $posts()->count(),
                'Réalisations' => $posts()->where('type', PublicationType::PersonalWork->value)->count(),
                'Opportunités' => $company->opportunities()->count(),
                'Abonnés' => DB::table('company_follows')->where('company_id', $company->id)->count(),
            ]) : [],
            'confirmedPeople' => $verified
                ? CompanyExperience::query()->where('company_id', $company->id)->where('status', 'confirmed')
                    ->where('show_on_company_page', true)
                    ->whereHas('user.profile', fn ($query) => $query->visibleTo($viewer))
                    ->with('user.profile')->latest('decided_at')->limit(12)->get()
                : collect(),
        ]);
    }
}
