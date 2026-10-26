<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\OpportunityResponse;
use App\Services\Opportunities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** Consulter une opportunité, la sauvegarder, y répondre et suivre sa réponse. Les réponses restent privées. */
class OpportunityController extends Controller
{
    public function show(Opportunity $opportunity)
    {
        $viewer = Auth::user();
        // Une opportunité qu'on n'a pas le droit de voir n'existe pas pour soi : 404.
        abort_unless(Gate::forUser($viewer)->allows('view', $opportunity), 404);
        $opportunity->load(['company', 'city', 'domain']);

        return view('pages.opportunity-show', [
            'opportunity' => $opportunity,
            'isOwner' => $viewer?->id === $opportunity->company->owner_id,
            'saved' => $viewer && DB::table('opportunity_saves')->where('user_id', $viewer->id)->where('opportunity_id', $opportunity->id)->exists(),
            'response' => $viewer ? OpportunityResponse::query()->where('opportunity_id', $opportunity->id)->where('user_id', $viewer->id)->first() : null,
            'canRespond' => $viewer && Gate::forUser($viewer)->allows('respond', $opportunity),
            'similar' => Opportunity::query()->open()->where('opportunities.id', '<>', $opportunity->id)->where('company_id', $opportunity->company_id)
                ->with(['company', 'city'])->latest('published_at')->limit(3)->get(),
        ]);
    }

    /** Mon espace Opportunités : ce que j'ai sauvegardé, mes réponses et leur état. */
    public function mine()
    {
        $user = Auth::user();
        $saved = Opportunity::query()->whereIn('opportunities.id', DB::table('opportunity_saves')->where('user_id', $user->id)->pluck('opportunity_id'))
            ->whereIn('status', [Opportunity::PUBLISHED, Opportunity::CLOSED])->whereHas('company', fn ($c) => $c->publiclyVisible())
            ->with(['company', 'city'])->latest('published_at')->get();
        $responses = OpportunityResponse::query()->where('user_id', $user->id)->where('state', '<>', OpportunityResponse::WITHDRAWN)
            ->with('opportunity.company')->latest()->get()->filter(fn ($r) => $r->opportunity->visibleTo($user));

        return view('pages.opportunity-mine', [
            'saved' => $saved,
            'responses' => $responses,
            'companies' => Company::query()->where('owner_id', $user->id)->where('status', '<>', 'suspended')->get(),
        ]);
    }

    public function save(Request $request, Opportunity $opportunity, Opportunities $service)
    {
        $user = Auth::user();
        $saved = DB::table('opportunity_saves')->where('user_id', $user->id)->where('opportunity_id', $opportunity->id)->exists();
        abort_unless(Gate::forUser($user)->allows('view', $opportunity), 404);
        $service->toggleSave($user, $opportunity, ! $saved);

        return back()->with('status', $saved ? 'Opportunité retirée de vos enregistrements.' : 'Opportunité sauvegardée. Retrouvez-la dans Mon espace.');
    }

    public function respond(Opportunity $opportunity)
    {
        $user = Auth::user();
        abort_unless(Gate::forUser($user)->allows('view', $opportunity), 404);
        abort_unless(Gate::forUser($user)->allows('respond', $opportunity), 403);
        $existing = OpportunityResponse::query()->where('opportunity_id', $opportunity->id)->where('user_id', $user->id)->first();
        if ($existing && $existing->state !== OpportunityResponse::WITHDRAWN) {
            return redirect()->route('opportunites.show', $opportunity)->with('status', 'Vous avez déjà répondu à cette opportunité.');
        }

        return view('pages.opportunity-respond', ['opportunity' => $opportunity->load('company', 'city'), 'profile' => $user->ensureProfile()]);
    }

    public function storeResponse(Request $request, Opportunity $opportunity, Opportunities $service)
    {
        $user = Auth::user();
        abort_unless(Gate::forUser($user)->allows('view', $opportunity), 404);
        $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimetypes:application/pdf'],
        ], ['message.max' => 'Votre message est trop long (1 000 caractères au plus).', 'attachment.*' => 'Joignez un document PDF de 5 Mo au plus.']);
        $service->respond($user, $opportunity, $request->input('message'), $request->file('attachment'));

        return redirect()->route('opportunites.show', $opportunity)->with('status', $opportunity->kind->applyLabel() === 'Postuler' ? 'Votre candidature a été envoyée à l’entreprise.' : 'Votre intérêt a été transmis à l’entreprise.');
    }

    public function withdraw(OpportunityResponse $response, Opportunities $service)
    {
        $service->withdraw(Auth::user(), $response);

        return redirect()->route('opportunites.show', $response->opportunity_id)->with('status', 'Votre réponse a été retirée.');
    }

    /** Pièce jointe d'une réponse : son auteur et l'entreprise émettrice seulement. */
    public function attachment(OpportunityResponse $response)
    {
        abort_unless($response->attachment_path && Gate::forUser(Auth::user())->allows('viewResponse', $response), 404);

        return response()->file(Storage::disk('local')->path($response->attachment_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reponse.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
