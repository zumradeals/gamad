<?php

use App\Enums\PublicationType;
use App\Models\User;
use App\Services\MatchingConditions;
use App\Services\Publications;

// Aides communes aux tests du Matching. Les recherches de correspondances sont confiées à l'exécution : ces aides la font avancer
// jusqu'au bout (drainMatching) pour tester les règles ; les tests de l'exécution, eux, ne drainent pas.

function matchingPreferences(User $user, array $changes = []): void
{
    $user->profile->update(['matching_preferences' => array_replace(MatchingConditions::defaults(), ['enabled' => true, 'intentions' => ['offer', 'teach', 'learn'], 'terms' => ['volunteer', 'paid', 'discuss'], 'remote' => true], $changes)]);
    $user->unsetRelation('profile');
    drainMatching();
}

function matchingPublication(User $user, PublicationType $type = PublicationType::Need, array $changes = [], array $data = [])
{
    $post = app(Publications::class)->publish($user, $type, ['body' => 'Une occasion pour la couture', 'skills' => ['Couture'], 'matching' => array_replace(MatchingConditions::publicationDefaults($type), ['enabled' => true, 'remote' => true, 'terms' => 'volunteer'], $changes), ...$data]);
    drainMatching();

    return $post->fresh();
}
