<?php

namespace App\Console\Commands;

use App\Enums\PlatformRole;
use App\Models\User;
use App\Services\AdminJournal;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('gamad:super-admin {email : Adresse e-mail du compte existant à promouvoir} {--raison= : Raison de la promotion (obligatoire, inscrite au journal)}')]
#[Description('Promeut un compte existant au rôle de super administrateur et inscrit l’acte, avec sa raison, au journal')]
class PromoteSuperAdmin extends Command
{
    public function handle(): int
    {
        $raison = trim((string) $this->option('raison'));

        if ($raison === '') {
            $this->error('L’option --raison="…" est obligatoire : elle est inscrite au journal d’administration.');

            return self::FAILURE;
        }

        $email = Str::lower(trim($this->argument('email')));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error('Aucun compte ne correspond à cette adresse e-mail. La personne doit d’abord créer son compte.');

            return self::FAILURE;
        }

        if ($user->isSuperAdmin()) {
            $this->info("{$user->name} est déjà super administrateur. Rien à faire.");

            return self::SUCCESS;
        }

        $previous = $user->platform_role;
        $user->forceFill(['platform_role' => PlatformRole::SuperAdmin])->save();

        AdminJournal::record(
            actor: null,
            action: 'platform_role.promoted_to_super_admin',
            subject: $user,
            reason: $raison,
            payload: [
                'previous_role' => $previous->value,
                'new_role' => PlatformRole::SuperAdmin->value,
            ] + AdminJournal::executionContext(),
        );

        $this->info("{$user->name} est maintenant super administrateur. L'acte est inscrit au journal d'administration.");

        return self::SUCCESS;
    }
}
