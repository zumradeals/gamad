<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyRegistry
{
    /** Créer un dossier réel ; aucun contrôle administratif n'est automatique. */
    public function submit(User $actor, array $data, array $documents): Company
    {
        abort_unless($actor->fresh()->isActive() && $actor->hasVerifiedEmail(), 403);
        $data = Validator::make($data, [
            'name' => 'required|string|min:2|max:160',
            'legal_name' => 'required|string|min:2|max:160',
            'registration_country' => 'required|string|size:2|alpha',
            'registration_number' => 'required|string|min:3|max:100',
            'sector' => 'required|string|min:2|max:120',
            'city' => 'nullable|string|max:120',
            'website' => 'nullable|url:http,https|max:255',
            'email' => 'nullable|email|max:255',
            'summary' => 'nullable|string|max:280',
            'description' => 'nullable|string|max:1800',
        ])->validate();

        Validator::make($documents, [
            'rccm' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'dfe' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'idu' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ])->validate();

        $data['registration_country'] = strtoupper(trim($data['registration_country']));
        $data['registration_number'] = mb_strtoupper(preg_replace('/\s+/', '', trim($data['registration_number'])));
        $data['name'] = trim($data['name']);
        $data['legal_name'] = trim($data['legal_name']);
        $data['sector'] = trim($data['sector']);

        if (Company::where('registration_country', $data['registration_country'])->where('registration_number', $data['registration_number'])->exists()) {
            throw ValidationException::withMessages(['registration_number' => 'Cette immatriculation est déjà utilisée sur GAMAD. Vous pouvez signaler une usurpation à la modération.']);
        }

        $stored = [];

        try {
            return DB::transaction(function () use ($actor, $data, $documents, &$stored) {
                $company = new Company($data);
                $company->owner_id = $actor->id;
                $company->slug = Str::slug($data['name']).'-'.strtolower(Str::random(8));
                $company->status = 'pending';
                $company->save();

                foreach (['rccm', 'dfe', 'idu'] as $kind) {
                    if (isset($documents[$kind]) === false) {
                        continue;
                    }
                    $file = $documents[$kind];
                    $path = $file->store('companies/'.$company->id.'/documents', 'local');
                    if ($path === false) {
                        throw ValidationException::withMessages(['rccm' => 'Le justificatif n’a pas pu être enregistré.']);
                    }
                    $stored[] = $path;
                    CompanyDocument::create([
                        'company_id' => $company->id,
                        'kind' => $kind,
                        'private_path' => $path,
                        'extension' => strtolower($file->extension() ?: 'pdf'),
                    ]);
                }

                app(Notifier::class)->companyModerators(NotificationKind::CompanyReviewRequested, $actor, $company);

                return $company;
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($stored);
            throw $exception;
        }
    }

    /**
     * Demande corrigée : seuls les nouveaux justificatifs remplacent les précédents.
     * Immatriculation, identité légale et propriétaire ne sont jamais modifiés.
     */
    public function resubmit(User $actor, Company $company, int $revision, array $documents): void
    {
        abort_unless($actor->fresh()->isActive() && $actor->hasVerifiedEmail() && $actor->id === $company->owner_id, 403);
        Validator::make($documents, [
            'rccm' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'dfe' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'idu' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ])->validate();
        $created = [];
        $oldPaths = [];

        try {
            DB::transaction(function () use ($actor, $company, $revision, $documents, &$created, &$oldPaths) {
                $row = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
                abort_unless($row->owner_id === $actor->id && $row->status === 'declined', 403);
                if ($row->revision !== $revision) {
                    throw ValidationException::withMessages(['rccm' => 'Le dossier a changé. Rechargez cette page.']);
                }
                foreach (['rccm', 'dfe', 'idu'] as $kind) {
                    if (empty($documents[$kind])) {
                        continue;
                    }
                    $file = $documents[$kind];
                    $path = $file->store('companies/'.$row->id.'/documents', 'local');
                    if ($path === false) {
                        throw ValidationException::withMessages(['rccm' => 'Le dépôt du document a échoué.']);
                    }
                    $created[] = $path;
                    $previous = CompanyDocument::where('company_id', $row->id)->where('kind', $kind)->first();
                    if ($previous) {
                        $oldPaths[] = $previous->private_path;
                        $previous->update(['private_path' => $path, 'extension' => strtolower($file->extension() ?: 'pdf')]);
                    } else {
                        CompanyDocument::create([
                            'company_id' => $row->id, 'kind' => $kind,
                            'private_path' => $path, 'extension' => strtolower($file->extension() ?: 'pdf'),
                        ]);
                    }
                }
                $row->forceFill([
                    'status' => 'pending', 'revision' => $row->revision + 1,
                    'review_note' => null, 'reviewer_id' => null, 'verified_at' => null,
                ])->save();
                app(Notifier::class)->companyModerators(NotificationKind::CompanyReviewRequested, $actor, $row);
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($created);
            throw $error;
        }
        Storage::disk('local')->delete($oldPaths);
    }

    /** Clôturer un signalement : suspendre séparément une page si l'usurpation est confirmée. */
    public function resolveReport(User $reviewer, CompanyReport $report, string $note): void
    {
        abort_unless($reviewer->fresh()->isActive() && $reviewer->isSuperAdmin(), 403);
        $note = trim($note);
        if (mb_strlen($note) < 8 || mb_strlen($note) > 500) {
            throw ValidationException::withMessages(['report' => 'Précisez une décision motivée (8 à 500 caractères).']);
        }

        DB::transaction(function () use ($reviewer, $report, $note) {
            $row = CompanyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === 'open', 422);
            $row->forceFill([
                'status' => 'resolved', 'resolution_note' => $note,
                'reviewer_id' => $reviewer->id, 'resolved_at' => now(),
            ])->save();
            AdminJournal::record($reviewer, 'companies.report_resolved', $row->company, $note);
        }, 3);
    }

    /**
     * Le vérificateur consulte le registre officiel hors GAMAD puis consigne sa décision.
     * L'existence légale ne signifie pas que l'identité du gestionnaire a été contrôlée.
     */
    public function review(User $reviewer, Company $company, int $revision, string $action, string $note = ''): void
    {
        abort_unless($reviewer->fresh()->isActive() && $reviewer->isSuperAdmin(), 403);
        abort_if($reviewer->id === $company->owner_id, 403);
        abort_unless(in_array($action, ['verify', 'decline', 'suspend'], true), 422);

        DB::transaction(function () use ($reviewer, $company, $revision, $action, $note) {
            $row = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            if ($row->revision !== $revision) {
                throw ValidationException::withMessages(['revision' => 'Ce dossier a changé. Rechargez la page.']);
            }
            abort_unless($row->status === ($action === 'suspend' ? 'verified' : 'pending'), 422);
            if ($action !== 'verify' && mb_strlen(trim($note)) < 5) {
                throw ValidationException::withMessages(['note' => 'Précisez le motif (5 caractères minimum).']);
            }
            $row->forceFill([
                'status' => match ($action) {
                    'verify' => 'verified',
                    'decline' => 'declined',
                    default => 'suspended',
                },
                'revision' => $row->revision + 1,
                'reviewer_id' => $reviewer->id,
                'verified_at' => $action === 'verify' ? now() : null,
                'review_note' => $action === 'verify' ? null : trim($note),
            ])->save();
            AdminJournal::record($reviewer, 'companies.'.$action, $row, $action === 'verify' ? null : trim($note), ['revision' => $row->revision]);
            app(Notifier::class)->company($row->owner, NotificationKind::CompanyReviewUpdated, $reviewer, $row);
        }, 3);
    }
}
