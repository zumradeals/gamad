<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\Notifier;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyExperienceService
{
    /**
     * Une personne ne peut référencer qu'une entreprise préalablement vérifiée.
     * Aucun statut « confirmé » n'est délivré au moment de la déclaration.
     */
    public function declare(User $user, array $data): CompanyExperience
    {
        abort_unless($user->fresh()->isActive() && $user->hasVerifiedEmail(), 403);

        $data = Validator::make($data, [
            'company_id' => ['required', 'uuid', Rule::exists('companies', 'id')],
            'relationship' => ['required', Rule::in(array_keys(CompanyExperience::TYPES))],
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'period' => ['required', 'string', 'min:2', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'show_on_company_page' => ['sometimes', 'boolean'],
        ])->validate();

        $company = Company::findOrFail($data['company_id']);
        if (! $company->verified()) {
            throw ValidationException::withMessages(['company_id' => 'Seule une entreprise dont l’immatriculation est vérifiée peut être citée dans votre parcours.']);
        }

        if (CompanyExperience::where('user_id', $user->id)->where('status', '!=', 'withdrawn')->count() >= 10) {
            throw ValidationException::withMessages(['company_id' => 'La limite de dix expériences professionnelles est atteinte pour le pilote.']);
        }

        $experience = new CompanyExperience([
            'relationship' => $data['relationship'],
            'title' => trim($data['title']),
            'period' => trim($data['period']),
            'description' => trim($data['description'] ?? '') ?: null,
            'show_on_company_page' => (bool) ($data['show_on_company_page'] ?? false),
        ]);
        $experience->user_id = $user->id;
        $experience->company_id = $company->id;
        $experience->status = 'pending';
        $experience->save();

        app(Notifier::class)->company($company->owner, NotificationKind::CompanyExpRequested, $user, $company, $experience);

        return $experience;
    }

    /** La page entreprise vérifiée confirme les faits précis, sans vérification supplémentaire de son gestionnaire au pilote. */
    public function decide(User $actor, CompanyExperience $experience, int $revision, string $decision): void
    {
        abort_unless($actor->fresh()->isActive() && $actor->hasVerifiedEmail(), 403);
        abort_unless(in_array($decision, ['confirm', 'decline'], true), 422);

        DB::transaction(function () use ($actor, $experience, $revision, $decision) {
            $row = CompanyExperience::whereKey($experience->id)->lockForUpdate()->firstOrFail();
            $company = Company::whereKey($row->company_id)->firstOrFail();
            abort_unless($actor->id === $company->owner_id, 403);
            abort_unless($company->verified(), 404);
            if ($row->revision !== $revision || $row->status !== 'pending') {
                throw ValidationException::withMessages(['experience' => 'Cette demande a déjà changé. Rechargez la page.']);
            }

            $row->forceFill([
                'status' => $decision === 'confirm' ? 'confirmed' : 'declined',
                'revision' => $row->revision + 1,
                'decided_by_id' => $actor->id,
                'decided_at' => now(),
            ])->save();
            app(Notifier::class)->company($row->user, $decision === 'confirm' ? NotificationKind::CompanyExpConfirmed : NotificationKind::CompanyExpDeclined, $actor, $company, $row);
        }, 3);
    }

    /** Une personne peut retirer sa déclaration et sa confirmation à tout moment. */
    public function withdraw(User $actor, CompanyExperience $experience): void
    {
        abort_unless($actor->fresh()->isActive(), 403);
        abort_unless($actor->id === $experience->user_id, 403);

        DB::transaction(function () use ($experience) {
            $row = CompanyExperience::whereKey($experience->id)->lockForUpdate()->firstOrFail();
            if ($row->status === 'withdrawn') {
                return;
            }
            $row->forceFill([
                'status' => 'withdrawn',
                'revision' => $row->revision + 1,
                'show_on_company_page' => false,
            ])->save();
        }, 3);
    }

    public function companyVisibility(User $actor, CompanyExperience $experience, bool $visible): void
    {
        abort_unless($actor->fresh()->isActive(), 403);
        abort_unless($actor->id === $experience->user_id, 403);

        DB::transaction(function () use ($experience, $visible) {
            $row = CompanyExperience::whereKey($experience->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->confirmed(), 422);
            $row->show_on_company_page = $visible;
            $row->save();
        }, 3);
    }
}
