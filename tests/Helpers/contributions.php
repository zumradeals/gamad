<?php

use App\Models\Company;
use App\Models\ContributionProposal;
use App\Models\Project;
use App\Models\Publication;
use App\Models\User;
use App\Models\Zumra;
use App\Services\ContributionNeeds;
use App\Services\ContributionProposals;
use App\Services\ProjectShowcase;
use App\Services\Projects;
use App\Services\Settings;
use Illuminate\Support\Str;

/** Ouvre ou ferme le module Contributions (C1, D-207), comme le fait le super administrateur dans Paramètres. */
function c1Module(string $etat = 'public', string $testeurs = ''): void
{
    app(Settings::class)->update(['contributions_module' => $etat, 'contributions_testers' => $testeurs], User::factory()->superAdmin()->create());
}

/** Ouvre ou ferme le module ZUMRA (Z1), dont dépendent les contributions à une ZUMRA ou à un projet. */
function c1ModuleZumra(string $etat = 'public'): void
{
    app(Settings::class)->update(['zumra_module' => $etat], User::factory()->superAdmin()->create());
}

function c1Entreprise(User $proprietaire, string $nom = 'Atelier Soleil'): Company
{
    $company = new Company(['name' => $nom, 'legal_name' => $nom.' SARL', 'registration_country' => 'CI', 'registration_number' => Str::random(12), 'sector' => 'Services', 'city' => 'Abidjan']);
    $company->slug = Str::slug($nom).'-'.strtolower(Str::random(8));
    $company->forceFill(['owner_id' => $proprietaire->id, 'status' => Company::VERIFIED, 'verified_at' => now()])->save();

    return $company;
}

/** Un projet accueilli par une ZUMRA, avec sa présentation publique publiée (P1). Le porteur est membre de la ZUMRA. */
function c1Projet(User $porteur, Zumra $zumra, string $nom = 'Académie numérique', bool $publie = true): Project
{
    ajouteMembre($zumra, $porteur);
    $project = app(Projects::class)->create($porteur, $zumra, ['name' => $nom, 'goal' => 'Former des jeunes au numérique avant la fin de l’année.']);

    if ($publie) {
        app(ProjectShowcase::class)->publish($porteur, $project->fresh(), ['summary' => 'Former des jeunes au numérique.']);
    }

    return $project->fresh();
}

/** Publie un besoin par le chemin réel (App\Services\ContributionNeeds). */
function c1Besoin(User $auteur, array $data = [], array $beneficiaire = ['person', null]): Publication
{
    return app(ContributionNeeds::class)->publish($auteur, $data + [
        'title' => 'Achat d’ordinateurs',
        'kind' => 'material',
        'details' => 'Équiper notre espace de formation en informatique pour 30 jeunes.',
        'beneficiary' => $beneficiaire,
        'visibility' => 'public',
    ]);
}

/** Propose une contribution par le chemin réel (App\Services\ContributionProposals). */
function c1Proposition(User $personne, array $data = []): ContributionProposal
{
    return app(ContributionProposals::class)->propose($personne, $data + [
        'kind' => 'skills',
        'offer' => 'Deux demi-journées par mois pour accompagner les jeunes.',
    ]);
}
