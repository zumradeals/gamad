<?php

namespace App\Livewire;

use App\Enums\NotificationKind;
use App\Models\Company;
use App\Models\CompanyAppeal;
use App\Services\AdminJournal;
use App\Services\CompanyRegistry;
use App\Services\Notifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Vérification des entreprises')]
class CompanyModeration extends Component
{
    public array $notes = [];

    private function authorizeReview(): void
    {
        abort_unless(Auth::user()?->isActive() && Auth::user()->isSuperAdmin(), 403);
    }

    public function mount(): void
    {
        $this->authorizeReview();
    }

    public function review(string $id, int $revision, string $action, CompanyRegistry $registry): void
    {
        $this->authorizeReview();
        $company = Company::findOrFail($id);
        $registry->review(Auth::user(), $company, $revision, $action, $this->notes[$id] ?? '');
        unset($this->notes[$id]);
        session()->flash('status', 'Décision enregistrée.');
    }

    public function decideAppeal(string $id, string $decision): void
    {
        $this->authorizeReview();
        abort_unless(in_array($decision, ['accept', 'reject'], true), 422);
        DB::transaction(function () use ($id, $decision) {
            $appeal = CompanyAppeal::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($appeal->status === 'pending', 409);
            $company = Company::whereKey($appeal->company_id)->lockForUpdate()->firstOrFail();
            if ($decision === 'accept' && $appeal->kind === 'appeal') {
                abort_unless(in_array($company->status, ['declined', 'suspended'], true), 422);
                $company->forceFill(['status' => 'pending', 'verified_at' => null, 'revision' => $company->revision + 1])->save();
            }
            if ($decision === 'accept' && $appeal->kind === 'report' && $company->verified()) {
                $company->forceFill(['status' => 'suspended', 'verified_at' => null, 'review_note' => 'Signalement accepté par la modération.', 'revision' => $company->revision + 1])->save();
            }
            $appeal->forceFill(['status' => $decision === 'accept' ? 'accepted' : 'rejected', 'reviewer_id' => Auth::id(), 'reviewed_at' => now()])->save();
            AdminJournal::record(Auth::user(), 'companies.appeal.'.$decision, $company, $appeal->reason, ['kind' => $appeal->kind, 'appeal' => $appeal->id]);
            app(Notifier::class)->push($company->owner, NotificationKind::CompanyReview, Auth::user());
        });
        session()->flash('status', 'Recours traité.');
    }

    public function render()
    {
        $this->authorizeReview();

        return view('livewire.company-moderation', [
            'appeals' => CompanyAppeal::with(['company', 'requester'])->where('status', 'pending')->latest()->limit(50)->get(),
            'companies' => Company::with(['owner', 'documents'])
                ->whereIn('status', ['pending', 'verified'])
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")
                ->latest('created_at')->limit(60)->get(),
        ]);
    }
}
