<?php

namespace App\Livewire;

use App\Services\LegalTexts;
use App\Services\Settings;
use App\Settings\Registry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Charte de conduite et mentions légales (Lot 5a, D-146) : le Président relit les projets de textes, renseigne les champs des
 * mentions légales et adopte les textes (ou retire l'adoption). Réservé au super administrateur ; chaque changement est inscrit
 * au journal d'administration.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Charte et mentions légales')]
class ParametresTextes extends Component
{
    public string $publisher = '';

    public string $director = '';

    public string $contact = '';

    public string $host = '';

    public ?string $saved = null;

    public function mount(Settings $settings): void
    {
        Gate::authorize('super-admin');

        $this->load($settings);
    }

    public function saveLegal(LegalTexts $texts, Settings $settings): void
    {
        Gate::authorize('super-admin');
        $this->reset('saved');
        $this->resetErrorBag();

        $this->validate([
            'publisher' => Registry::find('legal_publisher')->rules,
            'director' => Registry::find('legal_director')->rules,
            'contact' => Registry::find('legal_contact')->rules,
            'host' => Registry::find('legal_host')->rules,
        ], [], ['publisher' => 'éditeur', 'director' => 'responsable de la publication', 'contact' => 'adresse e-mail de contact', 'host' => 'hébergeur']);

        $texts->saveLegalValues(Auth::user(), [
            'legal_publisher' => $this->publisher,
            'legal_director' => $this->director,
            'legal_contact' => $this->contact,
            'legal_host' => $this->host,
        ]);

        // Des champs vidés après l'adoption : les mentions ne sont plus complètes, donc plus publiques ; on garde l'adoption en l'état.
        $this->load($settings);
        $this->saved = 'Mentions légales enregistrées.';
    }

    public function adoptCharter(LegalTexts $texts): void
    {
        $texts->adoptCharter(Auth::user());
        $this->saved = 'Charte adoptée : elle est publique et l’inscription demande de l’accepter.';
    }

    public function withdrawCharter(LegalTexts $texts): void
    {
        $texts->withdrawCharter(Auth::user());
        $this->saved = 'Adoption de la charte retirée : elle n’est plus publique et l’inscription ne la demande plus.';
    }

    public function adoptLegal(LegalTexts $texts): void
    {
        $texts->adoptLegal(Auth::user());
        $this->saved = 'Mentions légales adoptées : elles sont publiques.';
    }

    public function withdrawLegal(LegalTexts $texts): void
    {
        $texts->withdrawLegal(Auth::user());
        $this->saved = 'Adoption des mentions légales retirée : elles ne sont plus publiques.';
    }

    public function render(LegalTexts $texts)
    {
        Gate::authorize('super-admin');

        return view('livewire.parametres-textes', [
            'charterAt' => $texts->charterAdoptedAt(),
            'legalAt' => $texts->legalAdoptedAt(),
            'legalComplete' => $texts->legalComplete(),
        ]);
    }

    private function load(Settings $settings): void
    {
        $this->publisher = (string) $settings->get('legal_publisher');
        $this->director = (string) $settings->get('legal_director');
        $this->contact = (string) $settings->get('legal_contact');
        $this->host = (string) $settings->get('legal_host');
    }
}
