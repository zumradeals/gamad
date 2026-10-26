<?php

use App\Livewire\Fil;
use App\Livewire\ModerationAnnonces;
use App\Livewire\ParametresPublicite;
use App\Livewire\ResultatsCampagnes;
use App\Models\AdCampaign;
use App\Models\AdminAction;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Services\People;
use App\Services\Publicite\AdFeedback;
use App\Services\Publicite\AdServing;
use App\Services\Publicite\AdStats;
use App\Services\Publicite\Campaigns;
use App\Services\Settings;
use App\Support\PubliciteModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** Diffusion verrouillée, cartes sponsorisées, gestes de la personne et résultats confidentiels (D-210 à D-212). */
function difOuvrir(bool $diffusion = true): void
{
    $admin = User::factory()->superAdmin()->create();
    app(Settings::class)->update(['publicite_module' => 'public', 'publicite_min_audience' => 20, 'publicite_diffusion' => $diffusion ? 'open' : 'closed'], $admin);
}

/** Une campagne acceptée et en cours, contextuelle par défaut. */
function difCampagne(?User $annonceur = null, array $remplace = [], bool $acceptee = true): AdCampaign
{
    $annonceur ??= User::factory()->create();
    $s = app(Campaigns::class);
    $c = $s->save($annonceur, null, array_replace([
        'objective' => 'contact', 'cta' => 'exchange', 'title' => 'Cours de couture', 'body' => 'Apprenez à coudre en petit groupe.', 'audience_mode' => 'contextual',
        'budget_fcfa' => 30000, 'starts_on' => now('Africa/Abidjan')->addDay()->toDateString(), 'ends_on' => now('Africa/Abidjan')->addDays(10)->toDateString(),
    ], $remplace));
    if ($acceptee) {
        $c = $s->moderate(User::factory()->moderator()->create(), $s->submit($annonceur, $c), true);
        $c->forceFill(['starts_on' => now('Africa/Abidjan')->subDay(), 'ends_on' => now('Africa/Abidjan')->addDays(9)])->save();
    }

    return $c->fresh();
}

beforeEach(fn () => difOuvrir());

it('ne diffuse rien à personne tant que la diffusion payante est fermée, même service public et campagne acceptée', function () {
    difOuvrir(false);
    $c = difCampagne();
    $spectateur = User::factory()->create();

    expect(PubliciteModule::diffusionOpen())->toBeFalse()->and(app(AdServing::class)->forViewer($spectateur))->toBeNull()
        ->and(app(AdServing::class)->eligible($spectateur, $c))->toBeFalse();
    Livewire::actingAs($spectateur)->test(Fil::class)->assertDontSee('Sponsorisé');
    $this->actingAs($spectateur)->get(route('publicite.cta', $c))->assertNotFound();
    expect(DB::table('ad_daily_stats')->count())->toBe(0)->and(DB::table('ad_impressions')->count())->toBe(0);
});

it('n’ouvre la diffusion que par une confirmation écrite du super administrateur, au journal', function () {
    difOuvrir(false);
    $admin = User::factory()->superAdmin()->create();
    $page = Livewire::actingAs($admin)->test(ParametresPublicite::class)->assertSet('diffusion', 'closed')->set('diffusion', 'open')->call('save');
    $page->assertHasErrors('paymentConfirmed');
    expect(PubliciteModule::diffusionOpen())->toBeFalse();

    $page->set('paymentConfirmed', true)->call('save')->assertHasNoErrors();
    expect(PubliciteModule::diffusionOpen())->toBeTrue();
    expect(AdminAction::query()->where('action', 'setting.changed')->where('subject_id', 'publicite_diffusion')->exists())->toBeTrue();
});

it('règle l’audience minimale depuis Paramètres, dans les bornes', function () {
    $admin = User::factory()->superAdmin()->create();
    $page = Livewire::actingAs($admin)->test(ParametresPublicite::class)->set('minAudience', '5')->call('save');
    $page->assertHasErrors('minAudience');
    $page->set('minAudience', '150')->call('save')->assertHasNoErrors();
    expect(PubliciteModule::minimumAudience())->toBe(150);
});

it('choisit une annonce en cours pour une personne éligible, pas pour son annonceur, ni une campagne non acceptée ou hors dates', function () {
    $annonceur = User::factory()->create();
    $c = difCampagne($annonceur);
    $spectateur = User::factory()->create();
    $serving = app(AdServing::class);

    expect($serving->forViewer($spectateur)?->id)->toBe($c->id)->and($serving->forViewer($annonceur))->toBeNull();

    $future = difCampagne(User::factory()->create(), ['title' => 'Future'], acceptee: false);
    $c->forceFill(['ends_on' => now('Africa/Abidjan')->subDay()])->save();
    expect($serving->forViewer($spectateur))->toBeNull();
    expect($serving->eligible($spectateur, $future))->toBeFalse();
});

it('respecte blocages, suspensions et masquage', function () {
    $annonceur = User::factory()->create();
    $c = difCampagne($annonceur);
    $bloqueur = User::factory()->create();
    DB::table('person_blocks')->insert(['user_id' => $bloqueur->id, 'blocked_id' => $annonceur->id, 'created_at' => now()]);
    $serving = app(AdServing::class);
    expect($serving->eligible($bloqueur, $c))->toBeFalse();

    $autre = User::factory()->create();
    expect($serving->eligible($autre, $c))->toBeTrue();
    app(AdFeedback::class)->hide($autre, $c);
    expect($serving->eligible($autre, $c))->toBeFalse()->and($serving->forViewer($autre))->toBeNull();

    $annonceur->forceFill(['suspended_at' => now(), 'suspended_by' => User::factory()->superAdmin()->create()->id, 'suspension_reason' => 'Test'])->save();
    expect($serving->eligible(User::factory()->create(), $c->fresh()))->toBeFalse();
});

it('exige le consentement et la correspondance du profil pour une annonce personnalisée', function () {
    $couture = Skill::query()->create(['name' => 'Couture', 'normalized' => 'couture']);
    for ($i = 0; $i < 21; $i++) {
        $p = person("Couturière $i", offered: ['Couture'], city: 'Bouaké');
        $p->profile->forceFill(['ads_personalization' => true])->save();
    }
    $annonceur = User::factory()->create();
    $c = difCampagne($annonceur, ['audience_mode' => 'personalized', 'skills' => [$couture->id]]);
    $serving = app(AdServing::class);

    $consentant = Profile::query()->where('ads_personalization', true)->first()->user;
    $sansConsentement = person('Sans consentement', offered: ['Couture'], city: 'Bouaké');
    $horsCible = person('Hors cible', offered: ['Comptabilité'], city: 'Bouaké');
    $horsCible->profile->forceFill(['ads_personalization' => true])->save();

    expect($serving->eligible($consentant, $c))->toBeTrue()
        ->and($serving->eligible($sansConsentement, $c))->toBeFalse()
        ->and($serving->eligible($horsCible, $c))->toBeFalse();
    expect($serving->why($c))->toContain('autorisé les annonces personnalisées')->toContain('Couture')->and($serving->why(difCampagne(null, ['title' => 'Ctx'])))->toContain('n’utilise pas votre profil');
});

it('insère une carte Sponsorisé dans le Fil sans changer l’ordre des publications', function () {
    $c = difCampagne();
    $spectateur = person('Awa Traoré');
    $auteur = person('Moussa Koné');
    foreach (['Première', 'Deuxième', 'Troisième', 'Quatrième', 'Cinquième'] as $i => $mot) {
        publication($auteur, data: ['body' => "Publication $mot"])->forceFill(['created_at' => now()->subMinutes(10 - $i)])->save();
    }

    $html = Livewire::actingAs($spectateur)->test(Fil::class)->assertSee('Sponsorisé')->assertSee('Cours de couture')->assertSee('Pourquoi cette annonce ?')->html();
    $positions = collect(['Cinquième', 'Quatrième', 'Troisième', 'Deuxième', 'Première'])->map(fn ($m) => strpos($html, "Publication $m"))->all();
    expect($positions)->toBe(collect($positions)->sort()->values()->all());
    expect(strpos($html, 'Sponsorisé'))->toBeGreaterThan($positions[2])->and(strpos($html, 'Sponsorisé'))->toBeLessThan($positions[4]);
    expect($html)->toContain(route('publicite.cta', $c));
});

it('compte l’affichage une fois par personne et par jour, sans jamais enregistrer qui l’a vu côté annonceur', function () {
    $c = difCampagne();
    publication(person('Auteur Fil'), data: ['body' => 'Une publication.']);
    $spectateur = person('Awa Traoré');
    Livewire::actingAs($spectateur)->test(Fil::class);
    Livewire::actingAs($spectateur)->test(Fil::class);
    Livewire::actingAs(person('Moussa Koné'))->test(Fil::class);

    $stat = DB::table('ad_daily_stats')->where('ad_campaign_id', $c->id)->first();
    expect($stat->impressions)->toBe(2)->and($stat->interactions)->toBe(0);
    expect(Schema::getColumnListing('ad_daily_stats'))->not->toContain('user_id');
});

it('compte le clic sur le bouton puis mène à la page de l’annonceur ou au lien en https', function () {
    $annonceur = person('Annonceur Test');
    $c = difCampagne($annonceur, ['cta' => 'profile', 'objective' => 'visit']);
    $spectateur = User::factory()->create();

    $this->actingAs($spectateur)->get(route('publicite.cta', $c))->assertRedirect(route('personnes.show', $annonceur->profile->public_id));
    expect(DB::table('ad_daily_stats')->where('ad_campaign_id', $c->id)->value('interactions'))->toBe(1);

    $lien = difCampagne(null, ['cta' => 'link', 'objective' => 'visit', 'destination_url' => 'https://atelier.example/cours']);
    $this->actingAs($spectateur)->get(route('publicite.cta', $lien))->assertRedirect('https://atelier.example/cours');

    $entreprise = c1Entreprise($annonceurPro = User::factory()->create());
    $pro = difCampagne($annonceurPro, ['cta' => 'profile', 'objective' => 'visit', 'company_id' => $entreprise->id]);
    $this->actingAs($spectateur)->get(route('publicite.cta', $pro))->assertRedirect(route('entreprises.show', $entreprise));
});

it('compte comme contact la demande de relation envoyée à l’annonceur après un clic, une seule fois', function () {
    $annonceur = person('Annonceur Test');
    $c = difCampagne($annonceur, ['cta' => 'profile', 'objective' => 'visit']);
    $visiteuse = person('Visiteuse');
    $autre = person('Autre personne');

    $this->actingAs($visiteuse)->get(route('publicite.cta', $c));
    app(People::class)->request($visiteuse, $autre, 'Bonjour, sans lien avec l’annonce.');
    expect(DB::table('ad_daily_stats')->where('ad_campaign_id', $c->id)->value('contacts'))->toBe(0);

    app(People::class)->request($visiteuse, $annonceur, 'Bonjour, je voudrais en savoir plus.');
    expect(DB::table('ad_daily_stats')->where('ad_campaign_id', $c->id)->value('contacts'))->toBe(1);
});

it('masque et signale une annonce depuis le Fil : elle disparaît, la modération la voit signalée', function () {
    $c = difCampagne();
    publication(person('Auteur Fil'), data: ['body' => 'Une publication.']);
    $spectateur = person('Awa Traoré');
    $page = Livewire::actingAs($spectateur)->test(Fil::class)->assertSee('Cours de couture');
    $page->call('reportAd', $c->id, 'misleading')->assertSee('signalée à la modération')->assertDontSee('Cours de couture');
    expect(DB::table('ad_reports')->where('ad_campaign_id', $c->id)->value('reason'))->toBe('misleading')->and(DB::table('ad_hides')->count())->toBe(1);

    expect(fn () => app(AdFeedback::class)->report(User::factory()->create(), $c, 'inconnue'))->toThrow(ValidationException::class);
    $moderateur = User::factory()->moderator()->create();
    $this->actingAs($moderateur)->get('/moderation/annonces')->assertSee('Annonces signalées')->assertSee('Trompeuse ou arnaque (1)');
});

it('retire de la diffusion une annonce signalée, avec motif, journal et notification', function () {
    $annonceur = User::factory()->create();
    $c = difCampagne($annonceur);
    app(AdFeedback::class)->report(User::factory()->create(), $c, 'forbidden');
    $moderateur = User::factory()->moderator()->create();

    $page = Livewire::actingAs($moderateur)->test(ModerationAnnonces::class);
    $page->call('revoke', $c->id)->assertHasErrors('reason-'.$c->id);
    $page->set('reasons.'.$c->id, 'Produit réglementé non autorisé.')->call('revoke', $c->id)->assertSee('retirée de la diffusion');
    expect($c->fresh()->state)->toBe('refused')->and($c->fresh()->refusal_reason)->toBe('Produit réglementé non autorisé.');
    expect(AdminAction::query()->where('action', 'ad.revoked')->exists())->toBeTrue();
    expect(app(AdServing::class)->forViewer(User::factory()->create()))->toBeNull();
});

it('ne sert l’image qu’à l’annonceur, à la modération et à une personne à qui l’annonce est diffusée', function () {
    Storage::fake('local');
    $annonceur = User::factory()->create();
    $c = difCampagne($annonceur);
    $c->forceFill(['image_path' => 'ad-images/x.jpg'])->save();
    Storage::disk('local')->put('ad-images/x.jpg', 'jpeg');

    $this->actingAs(User::factory()->create())->get(route('publicite.image', $c))->assertOk();
    difOuvrir(false);
    $this->actingAs(User::factory()->create())->get(route('publicite.image', $c))->assertNotFound();
    $this->actingAs($annonceur)->get(route('publicite.image', $c))->assertOk();
});

it('masque tout chiffre sous le seuil et la courbe entière, sans jamais nommer une personne', function () {
    $annonceur = User::factory()->create();
    $c = difCampagne($annonceur);
    DB::table('ad_daily_stats')->insert(['ad_campaign_id' => $c->id, 'day' => now('Africa/Abidjan')->toDateString(), 'impressions' => 9, 'interactions' => 3, 'contacts' => 1]);

    $s = app(AdStats::class)->summary($annonceur, null, 30);
    expect($s['totals']['impressions'])->toBeNull()->and($s['totals']['contacts'])->toBeNull()->and($s['series'])->toBeNull()->and($s['rows'][0]['perContact'])->toBeNull();

    DB::table('ad_daily_stats')->where('ad_campaign_id', $c->id)->update(['impressions' => 120, 'interactions' => 40, 'contacts' => 12]);
    $s = app(AdStats::class)->summary($annonceur, null, 30);
    expect($s['totals']['impressions'])->toBe(120)->and($s['totals']['contacts'])->toBe(12)->and($s['series'])->toHaveCount(1)->and($s['rows'][0]['perContact'])->toBeInt();
});

it('calcule la dépense prévisionnelle au prorata, sans jamais la débiter', function () {
    $c = difCampagne();
    $c->forceFill(['starts_on' => now('Africa/Abidjan')->subDays(4), 'ends_on' => now('Africa/Abidjan')->addDays(5), 'budget_fcfa' => 10000])->save();
    expect(app(AdStats::class)->forecastSpend($c->fresh()))->toBe(5000);
    $c->forceFill(['starts_on' => now('Africa/Abidjan')->addDays(3), 'ends_on' => now('Africa/Abidjan')->addDays(8)])->save();
    expect(app(AdStats::class)->forecastSpend($c->fresh()))->toBe(0);
    expect(Schema::hasColumn('ad_campaigns', 'paid_fcfa'))->toBeFalse();
});

it('montre les résultats de l’annonceur seulement, avec la mention de masquage et de prévisionnel', function () {
    $annonceur = User::factory()->create();
    $c = difCampagne($annonceur);
    DB::table('ad_daily_stats')->insert(['ad_campaign_id' => $c->id, 'day' => now('Africa/Abidjan')->toDateString(), 'impressions' => 200, 'interactions' => 50, 'contacts' => 14]);
    $autre = difCampagne(User::factory()->create(), ['title' => 'Campagne d’autrui']);
    DB::table('ad_daily_stats')->insert(['ad_campaign_id' => $autre->id, 'day' => now('Africa/Abidjan')->toDateString(), 'impressions' => 999, 'interactions' => 5, 'contacts' => 5]);

    $page = Livewire::actingAs($annonceur)->test(ResultatsCampagnes::class)->assertSee('Cours de couture')->assertDontSee('Campagne d’autrui')->assertSee('Contacts reçus')->assertSee('prévisionnelles')->assertSee('aucun nom');
    $page->assertSee('200')->assertDontSee('999');
    $page->set('campaign', $c->id)->assertSee('Cours de couture');
});

it('explique qu’il n’y a aucun résultat tant que la diffusion payante est fermée', function () {
    difOuvrir(false);
    $annonceur = User::factory()->create();
    difCampagne($annonceur);
    Livewire::actingAs($annonceur)->test(ResultatsCampagnes::class)->assertSee('La diffusion payante n’est pas ouverte')->assertSee('Aucune donnée');
});

it('ferme les Résultats quand le service est désactivé et les refuse aux visiteurs', function () {
    $this->get('/espace/publicite/resultats')->assertRedirect('/login');
    app(Settings::class)->update(['publicite_module' => 'off'], User::factory()->superAdmin()->create());
    $this->actingAs(User::factory()->create())->get('/espace/publicite/resultats')->assertNotFound();
});
