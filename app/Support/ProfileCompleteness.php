<?php

namespace App\Support;

use App\Enums\PublicationType;
use App\Models\Profile;
use App\Models\User;

/**
 * Complétude du profil de Mon espace : cinq critères, chacun lu dans les données réellement enregistrées, à poids égal.
 * Rien n'est estimé : un critère vaut 20 % quand l'information existe, 0 % sinon.
 */
final class ProfileCompleteness
{
    /**
     * @return array{percent: int, done: int, total: int, items: array<int, array{key: string, label: string, done: bool, url: string}>, next: ?array{key: string, label: string, done: bool, url: string}}
     */
    public static function for(User $user, Profile $profile): array
    {
        $edit = route('espace.profil');
        $items = [
            ['key' => 'photo', 'label' => 'Photo de profil', 'done' => $profile->hasPhoto(), 'url' => $edit],
            ['key' => 'presentation', 'label' => 'Présentation', 'done' => filled($profile->bio) || filled($profile->introduction), 'url' => $edit.(filled($profile->bio) ? '#introduction' : '#bio')],
            ['key' => 'capacites', 'label' => 'Capacités', 'done' => $profile->offeredSkills->isNotEmpty(), 'url' => $edit.'#offered-titre'],
            ['key' => 'experiences', 'label' => 'Expériences', 'done' => ! empty($profile->experiences), 'url' => $edit.'#profile-experiences'],
            ['key' => 'realisations', 'label' => 'Réalisations', 'done' => $user->publications()->where('type', PublicationType::PersonalWork)->whereNull('zumra_id')->whereNull('company_id')->whereNull('hidden_at')->exists(), 'url' => route('agir.publier', 'ma-realisation')],
        ];

        $done = count(array_filter($items, fn (array $item) => $item['done']));

        return [
            'percent' => (int) round($done / count($items) * 100),
            'done' => $done,
            'total' => count($items),
            'items' => $items,
            'next' => collect($items)->firstWhere('done', false),
        ];
    }
}
