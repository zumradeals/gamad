<?php

namespace App\Services\AccountClosure;

use App\Core\Identity\Closure\AccountClosureParticipant;
use App\Core\Identity\Closure\ClosureContext;
use App\Models\AdCampaign;
use App\Models\Company;
use App\Models\OpportunityResponse;
use App\Models\Publication;
use App\Models\User;

/**
 * Fichiers de la personne que les clés étrangères font disparaître sans événement du modèle : médias de ses publications, CV de ses
 * candidatures, fichiers de ses entreprises et images de ses campagnes. Ils sont seulement désignés ici ; l'effacement a lieu après
 * la validation de la transaction.
 *
 * Écart corrigé par rapport à l'ancien comportement : les images des campagnes publicitaires (ad_campaigns, supprimées en cascade)
 * restaient sur le disque après la suppression du compte.
 */
class FilesClosure implements AccountClosureParticipant
{
    public function assertCanClose(User $user): void {}

    public function beforeDelete(User $user, ClosureContext $context): void
    {
        $context->purgeFiles('local', Publication::query()->where('author_id', $user->getKey())->whereNotNull('media_path')->pluck('media_path'));
        $context->purgeFiles('local', OpportunityResponse::query()->where('user_id', $user->getKey())->whereNotNull('attachment_path')->pluck('attachment_path'));
        $context->purgeFiles('local', AdCampaign::query()->where('user_id', $user->getKey())->whereNotNull('image_path')->pluck('image_path'));

        Company::query()->where('owner_id', $user->getKey())->get()->each(fn (Company $company) => $context->purgeFiles('local', $company->storedFilePaths()));
    }

    public function afterDelete(User $user, ClosureContext $context): void {}
}
