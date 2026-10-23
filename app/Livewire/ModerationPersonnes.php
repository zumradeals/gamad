<?php

namespace App\Livewire;

use App\Models\ModerationNotice;
use App\Models\User;
use App\Services\PersonModeration;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Modération des personnes (D-145, D-151) : envoyer un avis, suspendre après l'avis, classer l'avis, rétablir. Réservé au super
 * administrateur et aux modérateurs, revérifié à chaque affichage et à chaque acte. Chaque acte est inscrit au journal.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Modération des personnes')]
class ModerationPersonnes extends Component
{
    public const RESULTS = 8;

    public string $search = '';

    /** Motif saisi, par identifiant (personne pour un avis ou un rétablissement, avis pour une suspension). */
    public array $reasons = [];

    public function mount(): void
    {
        Gate::authorize('moderate');
    }

    public function issue(string $userId, PersonModeration $moderation): void
    {
        $this->act($userId, fn () => $moderation->issueNotice(Auth::user(), $this->person($userId), $this->reasons[$userId] ?? null), 'Avis envoyé. La personne a '.config('gamad.moderation.explanation_days').' jours pour s’expliquer.');
    }

    public function suspend(string $noticeId, PersonModeration $moderation): void
    {
        $this->act($noticeId, fn () => $moderation->suspend(Auth::user(), $this->notice($noticeId), $this->reasons[$noticeId] ?? null), 'Personne suspendue.');
    }

    public function closeNotice(string $noticeId, PersonModeration $moderation): void
    {
        $this->act($noticeId, fn () => $moderation->closeNotice(Auth::user(), $this->notice($noticeId), $this->reasons[$noticeId] ?? null), 'Avis classé sans suite. La personne en est informée.');
    }

    public function reinstate(string $userId, PersonModeration $moderation): void
    {
        $this->act($userId, fn () => $moderation->reinstate(Auth::user(), $this->person($userId), $this->reasons[$userId] ?? null), 'Compte rétabli. La personne en est informée.');
    }

    public function render()
    {
        Gate::authorize('moderate');

        $viewer = Auth::user();
        $key = Normalizer::key(Str::limit($this->search, 100, ''));

        $found = mb_strlen($key) < 2 ? collect() : User::query()
            ->active()
            ->where('name_search', 'like', Normalizer::likeContains($key))
            ->orderBy('name_search')->orderBy('id')
            ->limit(self::RESULTS)->get()
            ->filter(fn (User $person) => $viewer->can('sanction-person', $person))
            ->values();

        $openByUser = ModerationNotice::query()->open()->whereIn('user_id', $found->pluck('id')->all())->pluck('id', 'user_id');

        return view('livewire.moderation-personnes', [
            'found' => $found,
            'noticeOf' => $openByUser,
            'notices' => ModerationNotice::query()->open()->with(['user', 'issuer'])->orderBy('created_at')->orderBy('id')->get(),
            'suspended' => User::query()->whereNotNull('suspended_at')->with('suspender')->orderByDesc('suspended_at')->orderBy('id')->get(),
        ]);
    }

    private function person(string $id): User
    {
        abort_unless(Str::isUuid($id), 404);

        return User::query()->findOrFail($id);
    }

    private function notice(string $id): ModerationNotice
    {
        abort_unless(Str::isUuid($id), 404);

        return ModerationNotice::query()->findOrFail($id);
    }

    private function act(string $id, callable $action, string $done): void
    {
        Gate::authorize('moderate');
        $this->resetErrorBag();

        try {
            $action();
        } catch (ValidationException $e) {
            // L'erreur s'affiche sous la personne ou l'avis concerné.
            throw ValidationException::withMessages(["reasons.$id" => $e->errors()['reason'] ?? array_values($e->errors())[0]]);
        }

        unset($this->reasons[$id]);
        $this->search = '';
        session()->flash('status', $done);
    }
}
