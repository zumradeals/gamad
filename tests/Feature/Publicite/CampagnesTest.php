<?php

use App\Enums\AdState;
use App\Exceptions\AdException;
use App\Models\AdCampaign;
use App\Models\AdminAction;
use App\Models\AdModeration;
use App\Models\City;
use App\Models\Domain;
use App\Models\Skill;
use App\Models\User;
use App\Services\Publicite\AudienceEstimator;
use App\Services\Publicite\Campaigns;
use App\Services\Settings;
use App\Support\PubliciteModule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Modèle, règles d'état, audience, consentement et modération de GAMAD Publicité V1 (D-210 à D-212). */
function pubOuvrir(string $etat = 'public'): void
{
    app(Settings::class)->update(['publicite_module' => $etat, 'publicite_min_audience' => 20], User::factory()->superAdmin()->create());
}

/** n profils consentants qui proposent « Couture » à Bouaké. */
function pubConsentants(int $n, bool $consentement = true, string $competence = 'Couture', string $ville = 'Bouaké'): void
{
    for ($i = 0; $i < $n; $i++) {
        $p = person("Couturière $i $competence", offered: [$competence], city: $ville);
        $p->profile->forceFill(['ads_personalization' => $consentement])->save();
    }
}

function pubDonnees(User $annonceur, array $remplace = []): array
{
    $couture = Skill::query()->where('name', 'Couture')->first() ?? Skill::query()->create(['name' => 'Couture', 'normalized' => 'couture']);

    return array_replace([
        'objective' => 'contact', 'cta' => 'exchange', 'title' => 'Cours de couture', 'body' => 'Apprenez à coudre vos premiers vêtements en petit groupe.',
        'audience_mode' => 'personalized', 'skills' => [$couture->id], 'budget_fcfa' => 30000,
        'starts_on' => now('Africa/Abidjan')->addDays(2)->toDateString(), 'ends_on' => now('Africa/Abidjan')->addDays(16)->toDateString(),
    ], $remplace);
}

beforeEach(fn () => pubOuvrir());

it('estime l’audience parmi les seuls profils consentants et n’en montre que le niveau', function () {
    pubConsentants(21);
    pubConsentants(5, false);
    $annonceur = User::factory()->create();
    $couture = Skill::query()->where('name', 'Couture')->first();
    $estimator = app(AudienceEstimator::class);

    expect($estimator->count($annonceur, [$couture->id]))->toBe(21)
        ->and($estimator->level(19))->toBe('too_small')->and($estimator->level(20))->toBe('limited')->and($estimator->level(60))->toBe('fair')->and($estimator->level(200))->toBe('good');
    expect(AudienceEstimator::label('too_small'))->toBe('Trop restreinte');
});

it('exclut de l’audience l’annonceur, les personnes bloquées, suspendues ou non vérifiées', function () {
    pubConsentants(3);
    $couture = Skill::query()->where('name', 'Couture')->first();
    $annonceur = person('Annonceur Test', offered: ['Couture'], city: 'Bouaké');
    $annonceur->profile->forceFill(['ads_personalization' => true])->save();
    $autre = person('Bloquée', offered: ['Couture'], city: 'Bouaké');
    $autre->profile->forceFill(['ads_personalization' => true])->save();
    $susp = person('Suspendue', offered: ['Couture'], city: 'Bouaké', attributes: ['suspended_at' => now(), 'suspended_by' => User::factory()->superAdmin()->create()->id, 'suspension_reason' => 'Test']);
    $susp->profile->forceFill(['ads_personalization' => true])->save();
    $nonVerifie = person('Non vérifiée', offered: ['Couture'], city: 'Bouaké', attributes: ['email_verified_at' => null]);
    $nonVerifie->profile->forceFill(['ads_personalization' => true])->save();
    DB::table('person_blocks')->insert(['user_id' => $annonceur->id, 'blocked_id' => $autre->id, 'created_at' => now()]);

    expect(app(AudienceEstimator::class)->count($annonceur, [$couture->id]))->toBe(3);
});

it('applique les critères de domaine, de ville et « à distance »', function () {
    pubConsentants(4);
    pubConsentants(2, ville: 'Abidjan');
    $annonceur = User::factory()->create();
    $couture = Skill::query()->where('name', 'Couture')->first();
    $bouake = City::query()->where('name', 'Bouaké')->first();
    $estimator = app(AudienceEstimator::class);

    expect($estimator->count($annonceur, [$couture->id], [], [$bouake->id]))->toBe(4)
        ->and($estimator->count($annonceur, [$couture->id], [], [$bouake->id], true))->toBe(6)
        ->and($estimator->count($annonceur, [$couture->id], [Domain::query()->first()->id]))->toBe(0);
});

it('crée un brouillon, le complète puis l’envoie à la modération', function () {
    pubConsentants(21);
    $annonceur = User::factory()->create();
    $c = app(Campaigns::class)->save($annonceur, null, pubDonnees($annonceur));

    expect($c->state)->toBe('draft')->and($c->displayState())->toBe(AdState::Draft)->and($c->skills)->toHaveCount(1)->and($c->budget_fcfa)->toBe(30000);
    $c = app(Campaigns::class)->submit($annonceur, $c);
    expect($c->state)->toBe('review')->and($c->submitted_at)->not->toBeNull();
});

it('refuse l’envoi d’une campagne incomplète, avec une erreur par champ', function () {
    $annonceur = User::factory()->create();
    $c = app(Campaigns::class)->save($annonceur, null, ['audience_mode' => 'contextual']);

    try {
        app(Campaigns::class)->submit($annonceur, $c);
        $this->fail('Une campagne incomplète ne doit pas partir.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toContain('objective', 'title', 'body', 'cta', 'starts_on', 'budget_fcfa');
    }
    expect($c->fresh()->state)->toBe('draft');
});

it('refuse une campagne personnalisée dont l’audience est trop restreinte, jamais une campagne contextuelle', function () {
    pubConsentants(5);
    $annonceur = User::factory()->create();
    $c = app(Campaigns::class)->save($annonceur, null, pubDonnees($annonceur));

    expect(fn () => app(Campaigns::class)->submit($annonceur, $c))->toThrow(ValidationException::class);

    $ctx = app(Campaigns::class)->save($annonceur, null, pubDonnees($annonceur, ['audience_mode' => 'contextual', 'skills' => []]));
    expect(app(Campaigns::class)->submit($annonceur, $ctx)->state)->toBe('review');
    expect($ctx->skills)->toHaveCount(0);
});

it('ne compte pas les profils sans consentement : le ciblage personnalisé exige le consentement distinct', function () {
    pubConsentants(30, false);
    $annonceur = User::factory()->create();
    $c = app(Campaigns::class)->save($annonceur, null, pubDonnees($annonceur));

    expect(fn () => app(Campaigns::class)->submit($annonceur, $c))->toThrow(ValidationException::class);
});

it('valide le contenu : texte simple sans lien, bornes, destination en https, durée maximale', function (array $remplace, string $champ) {
    $annonceur = User::factory()->create();
    expect(fn () => app(Campaigns::class)->save($annonceur, null, pubDonnees($annonceur, $remplace)))->toThrow(ValidationException::class);
    try {
        app(Campaigns::class)->save($annonceur, null, pubDonnees($annonceur, $remplace));
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($champ);
    }
})->with([
    'lien dans le texte' => [['body' => 'Visitez https://exemple.com maintenant'], 'body'],
    'titre trop long' => [['title' => str_repeat('a', 41)], 'title'],
    'texte trop long' => [['body' => str_repeat('a', 181)], 'body'],
    'destination en http' => [['destination_url' => 'http://exemple.test'], 'destination_url'],
    'fin avant début' => [['ends_on' => '2020-01-01'], 'ends_on'],
    'trop longue' => [['ends_on' => '2040-01-01', 'starts_on' => '2039-01-01'], 'ends_on'],
    'objectif inconnu' => [['objective' => 'vendre'], 'objective'],
]);

it('exige un budget minimal en FCFA et des dates à venir à l’envoi', function (array $remplace, string $champ) {
    $annonceur = User::factory()->create();
    $c = app(Campaigns::class)->save($annonceur, null, pubDonnees($annonceur, ['audience_mode' => 'contextual', 'skills' => []] + $remplace));
    try {
        app(Campaigns::class)->submit($annonceur, $c);
        $this->fail('Envoi accepté à tort.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($champ);
    }
})->with([
    'budget trop bas' => [['budget_fcfa' => 500], 'budget_fcfa'],
    'début passé' => [['starts_on' => '2020-01-01', 'ends_on' => '2020-02-01'], 'starts_on'],
    'bouton inadapté' => [['objective' => 'contact', 'cta' => 'link'], 'cta'],
]);

it('n’accepte comme annonceur qu’une entreprise vérifiée que l’on possède', function () {
    $annonceur = User::factory()->create();
    $autre = User::factory()->create();
    $entreprise = c1Entreprise($annonceur);
    $etrangere = c1Entreprise($autre, 'Autre Atelier');
    $nonVerifiee = c1Entreprise($annonceur, 'Brouillonne');
    $nonVerifiee->forceFill(['status' => 'pending', 'verified_at' => null])->save();

    expect(app(Campaigns::class)->save($annonceur, null, pubDonnees($annonceur, ['company_id' => $entreprise->id]))->company_id)->toBe($entreprise->id);
    expect(fn () => app(Campaigns::class)->save($annonceur, null, pubDonnees($annonceur, ['company_id' => $etrangere->id])))->toThrow(AdException::class);
    expect(fn () => app(Campaigns::class)->save($annonceur, null, pubDonnees($annonceur, ['company_id' => $nonVerifiee->id])))->toThrow(AdException::class);
    expect(app(Campaigns::class)->advertisers($annonceur)['companies']->pluck('id')->all())->toBe([$entreprise->id]);
});

it('parcourt le cycle complet : examen, acceptation, prête, pause impossible hors diffusion, arrêt définitif', function () {
    $annonceur = User::factory()->create();
    $moderateur = User::factory()->moderator()->create();
    $service = app(Campaigns::class);
    $c = $service->save($annonceur, null, pubDonnees($annonceur, ['audience_mode' => 'contextual', 'skills' => []]));
    $c = $service->submit($annonceur, $c);
    expect($c->displayState())->toBe(AdState::Review);

    $c = $service->withdraw($c);
    expect($c->state)->toBe('draft');
    $c = $service->submit($annonceur, $c);
    $c = $service->moderate($moderateur, $c, true);
    // Diffusion payante fermée : acceptée, elle reste « Prête », jamais « Active ».
    expect($c->state)->toBe('ready')->and($c->displayState())->toBe(AdState::Ready);
    expect(fn () => $service->pause($c))->toThrow(AdException::class);

    $c = $service->stop($c);
    expect($c->displayState())->toBe(AdState::Done)->and($c->stopped_at)->not->toBeNull();
    expect(fn () => $service->resume($c))->toThrow(AdException::class);
    expect(fn () => $service->save($annonceur, $c, pubDonnees($annonceur)))->toThrow(AdException::class);
});

it('devient Active seulement si la diffusion payante est ouverte et que la campagne est en cours, et se met en pause puis reprend', function () {
    $annonceur = User::factory()->create();
    $moderateur = User::factory()->moderator()->create();
    $service = app(Campaigns::class);
    $c = $service->save($annonceur, null, pubDonnees($annonceur, ['audience_mode' => 'contextual', 'skills' => []]));
    $c = $service->moderate($moderateur, $service->submit($annonceur, $c), true);
    $c->forceFill(['starts_on' => now('Africa/Abidjan')->subDay(), 'ends_on' => now('Africa/Abidjan')->addDays(5)])->save();

    expect($c->displayState())->toBe(AdState::Ready);
    app(Settings::class)->update(['publicite_diffusion' => 'open'], User::factory()->superAdmin()->create());
    expect(PubliciteModule::diffusionOpen())->toBeTrue()->and($c->fresh()->displayState())->toBe(AdState::Active);

    $c = $service->pause($c);
    expect($c->displayState())->toBe(AdState::Paused);
    $c = $service->resume($c);
    expect($c->displayState())->toBe(AdState::Active);

    $c->forceFill(['ends_on' => now('Africa/Abidjan')->subDay()])->save();
    expect($c->fresh()->displayState())->toBe(AdState::Done);
});

it('renvoie en examen toute modification d’une annonce acceptée, en pause ou active', function () {
    $annonceur = User::factory()->create();
    $moderateur = User::factory()->moderator()->create();
    $service = app(Campaigns::class);
    $c = $service->save($annonceur, null, pubDonnees($annonceur, ['audience_mode' => 'contextual', 'skills' => []]));
    $c = $service->moderate($moderateur, $service->submit($annonceur, $c), true);

    $c = $service->save($annonceur, $c, pubDonnees($annonceur, ['audience_mode' => 'contextual', 'skills' => [], 'title' => 'Nouveau titre']));
    expect($c->state)->toBe('review')->and($c->title)->toBe('Nouveau titre');
    // En examen : plus modifiable, il faut d’abord retirer.
    expect(fn () => $service->save($annonceur, $c, pubDonnees($annonceur)))->toThrow(AdException::class);

    // Une modification invalide d’une annonce acceptée ne change rien.
    $prete = $service->moderate($moderateur, $c, true);
    expect(fn () => $service->save($annonceur, $prete, pubDonnees($annonceur, ['audience_mode' => 'contextual', 'skills' => [], 'budget_fcfa' => 10])))->toThrow(ValidationException::class);
    expect($prete->fresh()->state)->toBe('ready')->and($prete->fresh()->budget_fcfa)->toBe(30000);
});

it('exige un motif pour refuser, l’inscrit à l’historique et au journal, et rend la campagne modifiable', function () {
    $annonceur = User::factory()->create();
    $moderateur = User::factory()->moderator()->create();
    $service = app(Campaigns::class);
    $c = $service->submit($annonceur, $service->save($annonceur, null, pubDonnees($annonceur, ['audience_mode' => 'contextual', 'skills' => []])));

    expect(fn () => $service->moderate($moderateur, $c, false, 'ok'))->toThrow(ValidationException::class);
    $c = $service->moderate($moderateur, $c, false, 'Promesse de gain non permise.');
    expect($c->state)->toBe('refused')->and($c->refusal_reason)->toBe('Promesse de gain non permise.');
    expect(AdModeration::query()->where('ad_campaign_id', $c->id)->value('decision'))->toBe('refused');
    expect(AdminAction::query()->where('action', 'ad.refused')->value('reason'))->toBe('Promesse de gain non permise.');

    $c = $service->save($annonceur, $c, pubDonnees($annonceur, ['audience_mode' => 'contextual', 'skills' => [], 'body' => 'Texte corrigé.']));
    expect($c->state)->toBe('draft');
    expect(fn () => $service->moderate($moderateur, $c, true))->toThrow(AdException::class);
});

it('interdit à une personne de modérer sa propre annonce et réserve la décision à la modération', function () {
    $moderateur = User::factory()->moderator()->create();
    $service = app(Campaigns::class);
    $c = $service->submit($moderateur, $service->save($moderateur, null, pubDonnees($moderateur, ['audience_mode' => 'contextual', 'skills' => []])));

    expect(fn () => $service->moderate($moderateur, $c, true))->toThrow(AuthorizationException::class);
    $membre = User::factory()->create();
    expect(fn () => $service->moderate($membre, $c, true))->toThrow(AuthorizationException::class);
});

it('supprime un brouillon seulement, et duplique en un nouveau brouillon sans dates', function () {
    $annonceur = User::factory()->create();
    $moderateur = User::factory()->moderator()->create();
    $service = app(Campaigns::class);
    $c = $service->save($annonceur, null, pubDonnees($annonceur, ['audience_mode' => 'contextual', 'skills' => []]));
    $envoyee = $service->submit($annonceur, $c);
    expect(fn () => $service->delete($envoyee))->toThrow(AdException::class);

    $copie = $service->duplicate($annonceur, $envoyee);
    expect($copie->state)->toBe('draft')->and($copie->starts_on)->toBeNull()->and($copie->title)->toBe('Cours de couture')->and($copie->id)->not->toBe($envoyee->id);
    $service->delete($copie);
    expect(AdCampaign::query()->whereKey($copie->id)->exists())->toBeFalse();
});

it('limite le nombre de campagnes créées par jour', function () {
    config(['gamad.limits.ad_campaigns_per_day' => 2]);
    $annonceur = User::factory()->create();
    app(Campaigns::class)->save($annonceur, null, ['audience_mode' => 'contextual']);
    app(Campaigns::class)->save($annonceur, null, ['audience_mode' => 'contextual']);
    expect(fn () => app(Campaigns::class)->save($annonceur, null, ['audience_mode' => 'contextual']))->toThrow(AdException::class);
});

it('applique la Policy : l’annonceur gère, les autres ne voient rien, la modération voit les campagnes envoyées', function () {
    $annonceur = User::factory()->create();
    $autre = User::factory()->create();
    $moderateur = User::factory()->moderator()->create();
    $service = app(Campaigns::class);
    $brouillon = $service->save($annonceur, null, ['audience_mode' => 'contextual']);
    $envoyee = $service->submit($annonceur, $service->save($annonceur, null, pubDonnees($annonceur, ['audience_mode' => 'contextual', 'skills' => []])));

    expect(Gate::forUser($annonceur)->allows('manage', $brouillon))->toBeTrue()
        ->and(Gate::forUser($autre)->allows('manage', $brouillon))->toBeFalse()
        ->and(Gate::forUser($autre)->allows('view', $envoyee))->toBeFalse()
        ->and(Gate::forUser($moderateur)->allows('view', $brouillon))->toBeFalse()
        ->and(Gate::forUser($moderateur)->allows('view', $envoyee))->toBeTrue()
        ->and(Gate::forUser($moderateur)->allows('manage', $envoyee))->toBeFalse()
        ->and(Gate::forUser($moderateur)->allows('moderate', $envoyee))->toBeTrue()
        ->and(Gate::forUser($annonceur)->allows('moderate', $envoyee))->toBeFalse();
});

it('donne le droit de gérer au propriétaire d’une entreprise annonceuse', function () {
    $proprietaire = User::factory()->create();
    $entreprise = c1Entreprise($proprietaire);
    $c = app(Campaigns::class)->save($proprietaire, null, pubDonnees($proprietaire, ['company_id' => $entreprise->id]));
    expect(Gate::forUser($proprietaire)->allows('manage', $c))->toBeTrue()->and($c->advertiserName())->toBe($entreprise->name);
});

it('ferme tout : service désactivé, aucune campagne n’existe pour personne', function () {
    $annonceur = User::factory()->create();
    $c = app(Campaigns::class)->save($annonceur, null, ['audience_mode' => 'contextual']);
    pubOuvrir('off');

    expect(Gate::forUser($annonceur)->allows('manage', $c))->toBeFalse()
        ->and(Gate::forUser($annonceur)->allows('create', AdCampaign::class))->toBeFalse()
        ->and(Gate::forUser(User::factory()->superAdmin()->create())->allows('view', $c))->toBeFalse();
});

it('garde le consentement publicitaire désactivé par défaut et distinct du matching', function () {
    $p = person('Awa Traoré');
    expect($p->profile->ads_personalization)->toBeFalse()->and($p->profile->ads_personalization_at)->toBeNull();
    $p->profile->forceFill(['matching_preferences' => ['enabled' => true, 'discoverable' => true]])->save();
    expect($p->profile->fresh()->ads_personalization)->toBeFalse();
});

it('ne diffuse rien par défaut : la diffusion payante est fermée et le service ouvert ne l’ouvre pas', function () {
    expect(PubliciteModule::diffusionOpen())->toBeFalse()->and(PubliciteModule::minimumAudience())->toBe(20);
    app(Settings::class)->update(['publicite_min_audience' => 100], User::factory()->superAdmin()->create());
    expect(PubliciteModule::minimumAudience())->toBe(100);
});
