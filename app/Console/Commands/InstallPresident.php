<?php

namespace App\Console\Commands;

use App\Enums\PlatformRole;
use App\Models\User;
use App\Services\AdminJournal;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Appelée par deploy/install.sh : crée le compte du Président, vérifié et super administrateur, et l'inscrit
 * au journal d'administration (acteur « Installation »). Le mot de passe est lu sur l'entrée standard, jamais
 * dans les arguments de la commande (visibles par les autres comptes du serveur).
 */
#[Signature('gamad:installer-president {--name= : Nom affiché} {--email= : Adresse e-mail}')]
#[Description('Crée le compte du Président (vérifié, super administrateur) pendant l’installation ; mot de passe lu sur l’entrée standard')]
class InstallPresident extends Command
{
    /** @var (Closure(): string)|null */
    private static ?Closure $passwordReader = null;

    /**
     * @param  (Closure(): string)|null  $reader  Remplace la lecture de l'entrée standard (tests).
     */
    public static function readPasswordUsing(?Closure $reader): void
    {
        self::$passwordReader = $reader;
    }

    public function handle(): int
    {
        $validator = Validator::make(
            ['name' => $this->option('name'), 'email' => $this->option('email'), 'password' => $password = $this->readPassword()],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255'], 'password' => ['required', 'string', Password::default()]],
            [],
            ['name' => 'nom affiché', 'email' => 'adresse e-mail', 'password' => 'mot de passe'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $email = mb_strtolower(trim($this->option('email')));
        $existing = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($existing !== null) {
            if ($existing->isSuperAdmin()) {
                $this->info("Le compte {$existing->email} existe déjà et est super administrateur. Rien à faire.");

                return self::SUCCESS;
            }

            $this->error("Un compte existe déjà avec l'adresse {$existing->email} : il n'est pas super administrateur. Aucun compte créé.");

            return self::FAILURE;
        }

        $user = new User(['name' => trim($this->option('name')), 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now(), 'platform_role' => PlatformRole::SuperAdmin])->save();

        AdminJournal::record(
            actor: null,
            action: 'installation.super_admin_created',
            subject: $user,
            reason: 'Création du compte du Président par l\'installation',
            payload: ['new_role' => PlatformRole::SuperAdmin->value, 'email_verified' => true] + AdminJournal::executionContext(),
            actorLabel: AdminJournal::INSTALLATION_LABEL,
        );

        $this->info("Compte du Président créé : {$user->name} <{$user->email}> (super administrateur, e-mail vérifié).");

        return self::SUCCESS;
    }

    private function readPassword(): string
    {
        if (self::$passwordReader !== null) {
            return (self::$passwordReader)();
        }

        return rtrim((string) fgets(STDIN), "\r\n");
    }
}
