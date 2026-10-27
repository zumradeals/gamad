<?php

namespace App\Livewire;

use App\Livewire\Concerns\GuardsPubliciteModule;
use App\Models\AdCampaign;
use App\Models\Company;
use App\Services\Publicite\AdStats;
use App\Support\PubliciteModule;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Résultats » (D-210 à D-212) : affichages, interactions, contacts et dépenses prévisionnelles des campagnes de la personne et de ses
 * entreprises. Totaux agrégés seulement : aucun nom, aucun profil, aucune liste de personnes atteintes ; un chiffre sous le seuil est masqué.
 * Les « contacts » sont des demandes de relation que des personnes ont choisi d'envoyer après un clic sur l'annonce. Aucun export, aucun classement.
 */
#[Layout('components.layouts.app')]
#[Title('Résultats')]
class ResultatsCampagnes extends Component
{
    use GuardsPubliciteModule;

    #[Url(as: 'campagne', except: '')]
    public string $campaign = '';

    #[Url(as: 'periode', except: '30')]
    public string $period = '30';

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, ['7', '30', 'tout'], true) ? $period : '30';
    }

    public function render(AdStats $stats)
    {
        $user = Auth::user();
        $mine = AdCampaign::query()
            ->where(fn ($q) => $q->where('user_id', $user->getKey())->orWhereIn('company_id', Company::query()->where('owner_id', $user->getKey())->select('companies.id')))
            ->whereIn('state', ['ready', 'paused', 'done'])->orderBy('title')->get(['id', 'title']);
        $campaignId = $mine->contains('id', $this->campaign) ? $this->campaign : null;

        return view('livewire.resultats-campagnes', [
            'campaigns' => $mine,
            'summary' => $stats->summary($user, $campaignId, $this->period === 'tout' ? null : (int) $this->period),
            'threshold' => $stats->threshold(),
            'diffusionOpen' => PubliciteModule::diffusionOpen(),
        ]);
    }
}
