<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\AdminJournal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Espace')]
class Espace extends Component
{
    public string $password = '';

    public function deleteAccount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! Auth::guard('web')->validate(['email' => $user->email, 'password' => $this->password])) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        DB::transaction(function () use ($user) {
            // Les actes d'un modérateur ou du super administrateur sont toujours tracés (CLAUDE.md, règle 8).
            if ($user->can('moderate')) {
                AdminJournal::record($user, 'account.deleted_by_owner', $user, payload: ['role' => $user->platform_role->value]);
            }

            Auth::guard('web')->logout();
            $user->delete();
        });

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/', navigate: false);
    }

    public function render()
    {
        return view('livewire.espace', ['user' => Auth::user()]);
    }
}
