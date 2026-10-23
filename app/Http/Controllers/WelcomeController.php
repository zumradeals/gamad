<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Models\Zumra;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * L'accueil public (Lot 5a) : ce qu'est GAMAD, comment cela marche, et ce qui s'y fait vraiment. Rien n'y est inventé : les chiffres et
 * les exemples viennent de la base, et une section sans rien de réel à montrer n'apparaît pas.
 */
class WelcomeController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('fil');
        }

        $people = User::query()->active()->count();
        $zumras = Zumra::query()->count();
        $projects = Project::query()->count();

        return view('pages.welcome', [
            'figures' => array_filter([
                'personnes' => $people,
                'ZUMRA' => $zumras,
                'projets' => $projects,
            ]),
            'recentZumras' => Zumra::query()->with(['domain', 'city'])->orderByDesc('created_at')->orderByDesc('id')->limit(3)->get(),
            'recentProjects' => Project::query()->with('zumra')->orderByDesc('created_at')->orderByDesc('id')->limit(3)->get(),
        ]);
    }
}
