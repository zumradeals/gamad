<?php

namespace App\Services\Publicite;

use App\Models\AdCampaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Les gestes de la personne qui voit une annonce sponsorisée (D-211) : la masquer, ou la signaler à la modération. Une personne ne peut ni
 * masquer ni signaler sa propre annonce, ni une annonce qu'elle ne peut pas voir.
 */
class AdFeedback
{
    public const REASONS = [
        'misleading' => 'Trompeuse ou arnaque', 'offensive' => 'Offensante ou haineuse', 'forbidden' => 'Produit ou service interdit',
        'privacy' => 'Atteinte à la vie privée', 'spam' => 'Publicité répétitive ou indésirable', 'other' => 'Autre',
    ];

    public function __construct(private readonly AdServing $serving) {}

    public function hide(User $viewer, AdCampaign $campaign): void
    {
        $this->assertVisible($viewer, $campaign);
        DB::table('ad_hides')->insertOrIgnore(['ad_campaign_id' => $campaign->getKey(), 'user_id' => $viewer->getKey()]);
    }

    public function report(User $viewer, AdCampaign $campaign, string $reason): void
    {
        $this->assertVisible($viewer, $campaign);
        if (! array_key_exists($reason, self::REASONS)) {
            throw ValidationException::withMessages(['reason' => 'Choisissez une raison dans la liste.']);
        }
        DB::table('ad_reports')->updateOrInsert(['ad_campaign_id' => $campaign->getKey(), 'user_id' => $viewer->getKey()], ['reason' => $reason, 'created_at' => now()]);
        $this->hide($viewer, $campaign);
    }

    private function assertVisible(User $viewer, AdCampaign $campaign): void
    {
        abort_unless($this->serving->eligible($viewer, $campaign), 404);
    }
}
