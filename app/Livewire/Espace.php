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

    public string $name = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
    }

    /**
     * Chaque personne modifie son propre nom affiché, et lui seul. Les entrées passées du journal d'administration
     * gardent l'ancien nom (actor_label est une copie faite au moment de l'acte).
     */
    public function updateName(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->name = trim($this->name);
        $this->validate(['name' => ['required', 'string', 'max:255']], [], ['name' => 'nom affiché']);

        if ($this->name === $user->name) {
            session()->flash('status', 'Aucune modification.');
            $this->redirect(route('espace'), navigate: false);

            return;
        }

        DB::transaction(function () use ($user) {
            // Les actes d'un modérateur ou du super administrateur sont tracés (CLAUDE.md, règle 8, D-063) : l'entrée porte
            // l'ancien nom, celui sous lequel la personne était connue au moment de l'acte.
            if ($user->can('moderate')) {
                AdminJournal::record($user, 'account.renamed_by_owner', $user, payload: ['ancien' => $user->name, 'nouveau' => $this->name]);
            }

            $user->update(['name' => $this->name]);
        });

        session()->flash('status', 'Nom affiché enregistré.');
        $this->redirect(route('espace'), navigate: false);
    }

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

            // Aucun jeton de réinitialisation ne survit au compte : il est rattaché à l'adresse e-mail, non à l'identifiant.
            DB::table('password_reset_tokens')->whereRaw('lower(email) = ?', [mb_strtolower($user->email)])->delete();

            // Ni adresse IP ni navigateur ne survivent au compte : toutes ses sessions sont effacées (D-048).
            DB::table('sessions')->where('user_id', $user->id)->delete();

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
