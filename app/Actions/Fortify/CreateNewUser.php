<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\LegalTexts;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $charter = app(LegalTexts::class)->charterAdopted();

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            // Facultatif (D-217) : un pays actif de la liste, rien d'autre.
            'country_id' => ['nullable', 'uuid', Rule::exists('countries', 'id')->where('active', true)],
            // Une fois la charte adoptée (D-146), on s'inscrit en l'acceptant.
            'charter' => $charter ? ['accepted'] : ['nullable'],
        ], ['charter.accepted' => 'Acceptez la charte de conduite pour créer un compte.'])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        if (filled($input['country_id'] ?? null)) {
            $user->ensureProfile()->forceFill(['country_id' => $input['country_id']])->save();
        }

        // La date d'acceptation de la charte n'est gardée que si elle était adoptée à l'inscription.
        if ($charter) {
            $user->forceFill(['charter_accepted_at' => now()])->save();
        }

        return $user;
    }
}
