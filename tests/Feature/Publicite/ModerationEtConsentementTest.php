<?php

use App\Enums\NotificationKind;
use App\Livewire\ConsentementPublicite;
use App\Livewire\ModerationAnnonces;
use App\Models\AdCampaign;
use App\Models\AdminAction;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Publicite\AudienceEstimator;
use App\Services\Publicite\Campaigns;
use App\Services\Settings;
use Livewire\Livewire;

/** Modération préalable des annonces et consentement publicitaire distinct (D-210 à D-212). */
function modOuvrir(string $etat = 'public'): void
{
    app(Settings::class)->update(['publicite_module' => $etat, 'publicite_min_audience' => 20], User::factory()->superAdmin()->create());
}

function modEnvoyee(User $annonceur, string $titre = 'Cours de couture'): AdCampaign
{
    $s = app(Campaigns::class);

    return $s->submit($annonceur, $s->save($annonceur, null, [
        'objective' => 'contact', 'cta' => 'exchange', 'title' => $titre, 'body' => 'Apprenez à coudre en petit groupe.', 'audience_mode' => 'contextual',
        'budget_fcfa' => 30000, 'starts_on' => now('Africa/Abidjan')->addDays(2)->toDateString(), 'ends_on' => now('Africa/Abidjan')->addDays(16)->toDateString(),
    ]));
}

beforeEach(fn () => modOuvrir());

it('réserve la file des annonces à la modération et la ferme quand le service est fermé', function () {
    $this->get('/moderation/annonces')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/moderation/annonces')->assertForbidden();
    $this->actingAs(User::factory()->moderator()->create())->get('/moderation/annonces')->assertOk()->assertSee('Modération des annonces')->assertSee('Aucune annonce à examiner');
    modOuvrir('off');
    $this->actingAs(User::factory()->moderator()->create())->get('/moderation/annonces')->assertNotFound();
});

it('liste les annonces En examen avec leur aperçu étiqueté, du plus ancien au plus récent, et rien d’autre', function () {
    $annonceur = User::factory()->create();
    $ancienne = modEnvoyee($annonceur, 'Première annonce');
    $ancienne->forceFill(['submitted_at' => now()->subDay()])->save();
    modEnvoyee($annonceur, 'Seconde annonce');
    app(Campaigns::class)->save($annonceur, null, ['audience_mode' => 'contextual', 'title' => 'Mon brouillon']);

    $html = $this->actingAs(User::factory()->moderator()->create())->get('/moderation/annonces')->assertOk()->assertSee('Sponsorisé')->assertDontSee('Mon brouillon')->getContent();
    expect(strpos($html, 'Première annonce'))->toBeLessThan(strpos($html, 'Seconde annonce'));
});

it('accepte une annonce : elle devient Prête, l’annonceur est averti, la décision est au journal', function () {
    $annonceur = User::factory()->create();
    $c = modEnvoyee($annonceur);
    $moderateur = User::factory()->moderator()->create();

    Livewire::actingAs($moderateur)->test(ModerationAnnonces::class)->call('accept', $c->id)->assertSee('Annonce acceptée');
    expect($c->fresh()->state)->toBe('ready');
    expect(UserNotification::query()->where('user_id', $annonceur->id)->where('kind', NotificationKind::AdAccepted->value)->exists())->toBeTrue();
    expect(AdminAction::query()->where('action', 'ad.accepted')->where('actor_id', $moderateur->id)->exists())->toBeTrue();

    $this->actingAs($annonceur)->get('/notifications')->assertSee('Votre annonce a été acceptée par la modération');
});

it('refuse une annonce seulement avec un motif, que l’annonceur lit', function () {
    $annonceur = User::factory()->create();
    $c = modEnvoyee($annonceur);
    $moderateur = User::factory()->moderator()->create();
    $page = Livewire::actingAs($moderateur)->test(ModerationAnnonces::class);

    $page->call('refuse', $c->id)->assertHasErrors('reason-'.$c->id);
    expect($c->fresh()->state)->toBe('review');

    $page->set('reasons.'.$c->id, 'Promesse de gain non permise.')->call('refuse', $c->id)->assertSee('Annonce refusée');
    expect($c->fresh()->state)->toBe('refused')->and($c->fresh()->refusal_reason)->toBe('Promesse de gain non permise.');
    expect(UserNotification::query()->where('user_id', $annonceur->id)->where('kind', NotificationKind::AdRefused->value)->exists())->toBeTrue();
    $this->actingAs($annonceur)->get('/espace/publicite')->assertSee('Promesse de gain non permise.');
});

it('ne laisse pas la modération décider de sa propre annonce', function () {
    $moderateur = User::factory()->moderator()->create();
    $c = modEnvoyee($moderateur);
    Livewire::actingAs($moderateur)->test(ModerationAnnonces::class)->assertSee('C’est votre annonce')->call('accept', $c->id)->assertForbidden();
    expect($c->fresh()->state)->toBe('review');
});

it('refuse la décision à un membre ordinaire et à une annonce déjà décidée', function () {
    $c = modEnvoyee(User::factory()->create());
    Livewire::actingAs(User::factory()->create())->test(ModerationAnnonces::class)->assertForbidden();

    $moderateur = User::factory()->moderator()->create();
    $page = Livewire::actingAs($moderateur)->test(ModerationAnnonces::class);
    $page->call('accept', $c->id);
    $page->call('accept', $c->id)->assertSee('n’est pas possible dans l’état actuel');
});

it('montre les décisions aux modérateurs dans le journal, sans données de l’annonceur', function () {
    $c = modEnvoyee(User::factory()->create());
    $moderateur = User::factory()->moderator()->create();
    app(Campaigns::class)->moderate($moderateur, $c, true);

    $this->actingAs($moderateur)->get('/moderation/journal')->assertOk()->assertSee('a accepté une annonce publicitaire');
});

it('ajoute l’onglet Annonces à la modération quand le service est ouvert', function () {
    $moderateur = User::factory()->moderator()->create();
    $this->actingAs($moderateur)->get('/moderation')->assertSee(route('moderation.annonces'));
    modOuvrir('off');
    $this->actingAs($moderateur)->get('/moderation')->assertDontSee(route('moderation.annonces'));
});

it('désactive le consentement publicitaire par défaut, l’active puis le révoque avec son horodatage', function () {
    $moi = person('Awa Traoré');
    expect($moi->profile->ads_personalization)->toBeFalse();

    $c = Livewire::actingAs($moi)->test(ConsentementPublicite::class)->assertSet('enabled', false)->assertSee('Annonces personnalisées');
    $c->set('enabled', true)->assertSee('activées');
    expect($moi->profile->fresh()->ads_personalization)->toBeTrue()->and($moi->profile->fresh()->ads_personalization_at)->not->toBeNull();

    $c->set('enabled', false)->assertSee('désactivées');
    expect($moi->profile->fresh()->ads_personalization)->toBeFalse()->and($moi->profile->fresh()->ads_personalization_at)->toBeNull();
});

it('garde le consentement publicitaire séparé du matching et de la découverte par les entreprises', function () {
    $moi = person('Awa Traoré');
    Livewire::actingAs($moi)->test(ConsentementPublicite::class)->set('enabled', true);
    expect($moi->profile->fresh()->matching_preferences['enabled'] ?? false)->toBeFalse()->and($moi->profile->fresh()->matching_preferences['discoverable'] ?? false)->toBeFalse();
});

it('compte une personne dans l’audience seulement après son consentement, et plus après sa révocation', function () {
    $couture = Skill::query()->create(['name' => 'Couture', 'normalized' => 'couture']);
    $moi = person('Awa Traoré', offered: ['Couture']);
    $annonceur = User::factory()->create();
    $estimator = app(AudienceEstimator::class);
    expect($estimator->count($annonceur, [$couture->id]))->toBe(0);

    Livewire::actingAs($moi)->test(ConsentementPublicite::class)->set('enabled', true);
    expect($estimator->count($annonceur, [$couture->id]))->toBe(1);
    Livewire::actingAs($moi)->test(ConsentementPublicite::class)->set('enabled', false);
    expect($estimator->count($annonceur, [$couture->id]))->toBe(0);
});

it('n’affiche et n’accepte le consentement que si le service est ouvert à la personne', function () {
    $moi = person('Awa Traoré');
    $this->actingAs($moi)->get('/espace?rubrique=compte')->assertSee('Annonces personnalisées');
    modOuvrir('off');
    $this->actingAs($moi)->get('/espace?rubrique=compte')->assertDontSee('Annonces personnalisées');
    Livewire::actingAs($moi)->test(ConsentementPublicite::class)->set('enabled', true)->assertNotFound();
    expect($moi->profile->fresh()->ads_personalization)->toBeFalse();
});
