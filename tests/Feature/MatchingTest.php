<?php

use App\Enums\NotificationKind;
use App\Enums\PublicationType;
use App\Livewire\MatchSuggestions;
use App\Livewire\ModifierProfil;
use App\Livewire\PagePublication;
use App\Livewire\PublierPublication;
use App\Models\City;
use App\Models\Conversation;
use App\Models\MatchSuggestion;
use App\Models\Publication;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Matching;
use App\Services\MatchingConditions;
use App\Services\Moderation;
use App\Services\People;
use App\Services\Publications;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function matchingPreferences(User $user, array $changes = []): void
{
    $user->profile->update(['matching_preferences' => array_replace(MatchingConditions::defaults(), ['enabled' => true, 'intentions' => ['offer', 'teach', 'learn'], 'terms' => ['volunteer', 'paid', 'discuss'], 'remote' => true], $changes)]);
    $user->unsetRelation('profile');
}

function matchingPublication(User $user, PublicationType $type = PublicationType::Need, array $changes = [], array $data = [])
{
    return app(Publications::class)->publish($user, $type, ['body' => 'Une occasion pour la couture', 'skills' => ['Couture'], 'matching' => array_replace(MatchingConditions::publicationDefaults($type), ['enabled' => true, 'remote' => true, 'terms' => 'volunteer'], $changes), ...$data]);
}

beforeEach(function () {
    config(['gamad.initial_experience' => true]);
    $this->author = person('Atelier de couture', ['Couture']);
    $this->helper = person('Personne disponible', ['Couture'], ['Couture']);
    $this->engine = app(Matching::class);
});

it('conserve les personnes et publications anciennes sans matching et sans notification', function () {
    publication($this->author, data: ['skills' => ['Couture']]);
    expect($this->engine->suggestions($this->helper))->toBeEmpty()->and(MatchSuggestion::count())->toBe(0)->and(UserNotification::count())->toBe(0);
    Livewire::actingAs($this->helper)->test(MatchSuggestions::class)->assertSeeText('Choisir mes préférences')->assertDontSeeText('Atelier de couture');
});

it('suggère une complémentarité dans les deux sens en expliquant les critères sans créer de contact', function () {
    matchingPreferences($this->helper);
    matchingPreferences($this->author);
    $post = matchingPublication($this->author);
    $items = $this->engine->suggestions($this->helper);
    expect($items)->toHaveCount(1)->and($items[0]['reasons'])->toContain('Capacité commune : Couture', 'À distance possible des deux côtés', 'Bénévole / gratuit')
        ->and($items[0]['url'])->toBe(route('publications.show', $post))->and(Conversation::count())->toBe(0)->and(UserNotification::count())->toBe(0);
    $items = $this->engine->suggestions($this->author);
    expect($items)->toHaveCount(1)->and($items[0]['title'])->toBe($this->helper->name)->and($items[0]['url'])->toBe(route('personnes.show', $this->helper->profile));
});

it('respecte les intentions et les conditions choisies sans supposer qu’un bénévole cherche une prestation', function () {
    matchingPreferences($this->helper, ['intentions' => ['offer'], 'terms' => ['volunteer']]);
    matchingPublication($this->author, changes: ['terms' => 'paid']);
    matchingPublication($this->author, PublicationType::LearningWish);
    matchingPublication($this->author, PublicationType::Knowledge);
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
    $free = matchingPublication($this->author);
    expect($this->engine->suggestions($this->helper)->pluck('url')->all())->toBe([route('publications.show', $free)]);
});

it('ne devine pas les compétences à partir du texte ni des domaines', function () {
    matchingPreferences($this->helper);
    publication($this->author, data: ['body' => 'Couture', 'skills' => []]);
    matchingPublication($this->author, data: ['skills' => ['Comptabilité']]);
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
});

it('utilise uniquement la localité choisie ou une distance acceptée des deux côtés', function () {
    $city = City::where('name', 'Cocody')->firstOrFail();
    $other = City::where('name', 'Bouaké')->firstOrFail();
    matchingPreferences($this->helper, ['zones' => [$city->id], 'remote' => false]);
    $local = matchingPublication($this->author, changes: ['remote' => false], data: ['city_id' => $city->id]);
    matchingPublication($this->author, changes: ['remote' => true], data: ['city_id' => $other->id]);
    expect($this->engine->suggestions($this->helper)->pluck('url')->all())->toBe([route('publications.show', $local)]);
    matchingPreferences($this->helper, ['zones' => [$city->id], 'remote' => true]);
    expect($this->engine->suggestions($this->helper))->toHaveCount(2);
});

it('ne déduit pas que deux communes d’un même district sont le même lieu', function () {
    $cocody = City::where('name', 'Cocody')->firstOrFail();
    $abobo = City::where('name', 'Abobo')->firstOrFail();
    matchingPreferences($this->helper, ['zones' => [$cocody->id], 'remote' => false]);
    matchingPublication($this->author, changes: ['remote' => false], data: ['city_id' => $abobo->id]);
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
});

it('écarte les périodes et jours incompatibles ainsi que les personnes indisponibles', function () {
    $start = today()->next('Monday');
    matchingPreferences($this->helper, ['from' => $start->copy()->toDateString(), 'until' => $start->copy()->addDays(3)->toDateString(), 'days' => [1]]);
    matchingPublication($this->author, changes: ['from' => $start->copy()->addDays(6)->toDateString(), 'until' => $start->copy()->addDays(8)->toDateString()]);
    matchingPublication($this->author, changes: ['days' => [2]]);
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
    matchingPublication($this->author, changes: ['from' => $start->copy()->toDateString(), 'until' => $start->copy()->addDays(3)->toDateString(), 'days' => [1]]);
    expect($this->engine->suggestions($this->helper))->toHaveCount(1);
    matchingPreferences($this->helper, ['available' => false]);
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
});

it('propose une formation uniquement aux personnes souhaitant apprendre cette capacité', function () {
    matchingPreferences($this->helper, ['intentions' => ['learn'], 'terms' => ['paid']]);
    $training = matchingPublication($this->author, PublicationType::Offer, ['activity' => 'training', 'terms' => 'paid', 'budget' => 'Prix à discuter']);
    matchingPublication($this->author, PublicationType::Offer, ['activity' => 'service', 'terms' => 'paid']);
    expect($this->engine->suggestions($this->helper)->pluck('url')->all())->toBe([route('publications.show', $training)]);
    matchingPreferences($this->helper, ['intentions' => ['offer'], 'terms' => ['paid']]);
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
});

it('rapproche une offre de prestation et un besoin compatible sans confondre cela avec apprendre', function () {
    matchingPreferences($this->author, ['intentions' => ['learn'], 'terms' => ['paid']]);
    matchingPreferences($this->helper, ['intentions' => ['learn'], 'terms' => ['paid']]);
    $need = matchingPublication($this->author, changes: ['terms' => 'paid']);
    $offer = matchingPublication($this->helper, PublicationType::Offer, ['terms' => 'paid']);
    expect($this->engine->suggestions($this->author)->pluck('url')->all())->toBe([route('publications.show', $offer)]);
    expect($this->engine->suggestions($this->helper)->pluck('url')->all())->toBe([route('publications.show', $need)]);
});

it('ne mélange pas une offre payante et un besoin bénévole', function () {
    matchingPreferences($this->author, ['intentions' => ['learn']]);
    matchingPreferences($this->helper, ['intentions' => ['learn']]);
    matchingPublication($this->author, changes: ['terms' => 'volunteer']);
    matchingPublication($this->helper, PublicationType::Offer, ['terms' => 'paid']);
    expect($this->engine->suggestions($this->author))->toBeEmpty()->and($this->engine->suggestions($this->helper))->toBeEmpty();
});

it('retire les suggestions clôturées expirées résolues ou masquées même si un reçu existe déjà', function () {
    matchingPreferences($this->helper);
    $post = matchingPublication($this->author);
    expect($this->engine->suggestions($this->helper))->toHaveCount(1);
    app(Publications::class)->resolve($this->author, $post);
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
    $other = matchingPublication($this->author, PublicationType::Knowledge);
    expect($this->engine->suggestions($this->helper))->toHaveCount(1);
    app(Moderation::class)->hide(User::factory()->superAdmin()->create(), $other, 'Motif de recette');
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
    matchingPublication($this->author, changes: ['until' => today()->subDay()->toDateString()]);
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
});

it('respecte les blocages des deux côtés même pour un compte de modération', function () {
    matchingPreferences($this->helper);
    matchingPublication($this->author);
    expect($this->engine->suggestions($this->helper))->toHaveCount(1);
    app(People::class)->block($this->author, $this->helper, true);
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
    expect(Conversation::count())->toBe(0);
});

it('ignore une piste durablement et interdit à un tiers de l’ignorer', function () {
    matchingPreferences($this->helper);
    matchingPublication($this->author);
    $row = $this->engine->suggestions($this->helper)[0]['suggestion'];
    expect(fn () => $this->engine->ignore($this->author, $row))->toThrow(AuthorizationException::class);
    Livewire::actingAs($this->helper)->test(MatchSuggestions::class)->call('ignore', $row->id)->assertSeeText('Cette piste a été retirée')->assertDontSeeText('Capacité commune');
    expect($this->engine->suggestions($this->helper))->toBeEmpty()->and($row->fresh()->ignored_at)->not->toBeNull();
});

it('produit une alerte interne générique une seule fois et regroupe les correspondances nouvelles', function () {
    matchingPreferences($this->helper, ['notify' => true]);
    matchingPublication($this->author);
    matchingPublication($this->author);
    $alerts = UserNotification::where('user_id', $this->helper->id)->where('kind', NotificationKind::MatchFound);
    expect((clone $alerts)->count())->toBe(1)->and((clone $alerts)->sole()->publication_id)->toBeNull()->and((clone $alerts)->sole()->actor_id)->toBeNull();
    $this->engine->suggestions($this->helper);
    (clone $alerts)->update(['read_at' => now()]);
    $this->engine->suggestions($this->helper);
    expect((clone $alerts)->count())->toBe(1);
    $this->actingAs($this->helper)->get(route('notifications.open', (clone $alerts)->sole()))->assertRedirect(route('espace', ['rubrique' => 'pistes']).'#suggestions');
});

it('permet de désactiver les alertes et limite les nouvelles alertes à cinq par jour', function () {
    matchingPreferences($this->helper, ['notify' => true]);
    foreach (range(1, 7) as $i) {
        matchingPublication($this->author, data: ['body' => 'Besoin '.$i]);
        UserNotification::where('user_id', $this->helper->id)->update(['read_at' => now()]);
    }
    expect(UserNotification::where('user_id', $this->helper->id)->where('kind', NotificationKind::MatchFound)->count())->toBe(5);
    matchingPreferences($this->helper, ['notify' => false]);
    $this->engine->profileChanged($this->helper->id);
    matchingPublication($this->author);
    expect(UserNotification::where('user_id', $this->helper->id)->where('kind', NotificationKind::MatchFound)->count())->toBe(5);
});

it('valide les préférences et les conditions côté serveur sans paiement implicite', function () {
    $validator = app(MatchingConditions::class);
    foreach ([['enabled' => true], ['intentions' => ['hiring_score']], ['from' => 'demain'], ['from' => '2026-12-30', 'until' => '2026-12-01'], ['days' => [9]], ['zones' => ['bad']], ['unknown' => 'secret']] as $changes) {
        expect(fn () => $validator->preferences(array_replace(MatchingConditions::defaults(), $changes)))->toThrow(ValidationException::class);
    }
    expect(fn () => matchingPublication($this->author, changes: ['budget' => '10 000']))->toThrow(ValidationException::class);
    expect(fn () => matchingPublication($this->author, data: ['skills' => []]))->toThrow(ValidationException::class);
    expect(fn () => matchingPublication($this->author, PublicationType::Share))->toThrow(ValidationException::class);
});

it('enregistre les préférences et publie les conditions par les formulaires existants', function () {
    Livewire::actingAs($this->helper)->test(ModifierProfil::class)->set('matching_preferences.enabled', true)->set('matching_preferences.intentions', ['offer'])->set('matching_preferences.terms', ['paid'])->set('matching_preferences.remote', true)->call('save')->assertHasNoErrors();
    Livewire::actingAs($this->author)->test(PublierPublication::class, ['slug' => 'besoin'])->set('body', 'Recherche un couturier pour une commande.')->set('skills', ['Couture'])->set('matching.enabled', true)->set('matching.terms', 'paid')->set('matching.budget', 'Budget à discuter')->set('matching.remote', true)->call('publish')->assertHasNoErrors();
    $post = Publication::sole();
    expect($post->gestureLabel())->toBe('Proposer mes services')->and(Conversation::count())->toBe(0);
    $this->actingAs($this->helper)->get(route('publications.show', $post))->assertOk()->assertSeeText('Rémunéré / payant')->assertSeeText('Proposer mes services');
});

it('permet au propriétaire de fermer une offre au matching sans fermer les échanges existants', function () {
    matchingPreferences($this->helper);
    $post = matchingPublication($this->author, PublicationType::Knowledge);
    expect($this->engine->suggestions($this->helper))->toHaveCount(1);
    Livewire::actingAs($this->helper)->test(PagePublication::class, ['publication' => $post])->call('closeMatching')->assertForbidden();
    Livewire::actingAs($this->author)->test(PagePublication::class, ['publication' => $post])->call('closeMatching')->assertSeeText('Cette publication ne produit plus de suggestions');
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
});

it('ne se propose pas soi-même et ne conserve aucun reçu après suppression du compte', function () {
    matchingPreferences($this->helper);
    matchingPublication($this->helper);
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
    matchingPublication($this->author);
    expect(MatchSuggestion::count())->toBe(1);
    fermerCompte($this->helper);
    expect(MatchSuggestion::count())->toBe(0);
});

it('retire immédiatement une personne devenue inactive et ne propose pas les personnes bloquées à la modération', function () {
    matchingPreferences($this->helper);
    $post = matchingPublication($this->author);
    expect($this->engine->suggestions($this->helper))->toHaveCount(1);
    $this->author->forceFill(['email_verified_at' => null])->save();
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
    $this->author->forceFill(['email_verified_at' => now()])->save();
    $moderator = User::factory()->superAdmin()->create();
    $moderator->profile->offeredSkills()->attach($this->helper->profile->offeredSkills->first()->id, ['kind' => 'offered', 'position' => 0]);
    matchingPreferences($moderator);
    app(People::class)->block($this->author, $moderator, true);
    expect($this->engine->suggestions($moderator))->toBeEmpty();
});

it('retire les pistes lorsque les préférences sont désactivées et enlève l’alerte de matching non lue', function () {
    matchingPreferences($this->helper, ['notify' => true]);
    matchingPublication($this->author);
    expect(UserNotification::where('kind', NotificationKind::MatchFound)->count())->toBe(1);
    Livewire::actingAs($this->helper)->test(ModifierProfil::class)->set('matching_preferences.enabled', false)->call('save')->assertHasNoErrors();
    expect(UserNotification::where('kind', NotificationKind::MatchFound)->whereNull('read_at')->count())->toBe(0)->and(UserNotification::where('kind', NotificationKind::MatchFound)->count())->toBe(1)->and($this->engine->suggestions($this->helper->fresh()))->toBeEmpty();
});

it('ne produit pas de correspondance si le seul jour commun ne tombe pas dans la période commune', function () {
    $tuesday = today()->next('Tuesday');
    matchingPreferences($this->helper, ['from' => $tuesday->toDateString(), 'until' => $tuesday->toDateString(), 'days' => [1]]);
    matchingPublication($this->author, changes: ['from' => $tuesday->toDateString(), 'until' => $tuesday->toDateString(), 'days' => [1]]);
    expect($this->engine->suggestions($this->helper))->toBeEmpty();
});

it('refuse le matching collectif même par un appel direct au service', function () {
    config(['gamad.initial_experience' => false]);
    $zumra = zumra($this->author);
    expect(fn () => app(Publications::class)->publish($this->author, PublicationType::Need, ['body' => 'Besoin collectif', 'skills' => ['Couture'], 'matching' => array_replace(MatchingConditions::publicationDefaults(PublicationType::Need), ['enabled' => true, 'remote' => true])], $zumra))->toThrow(ValidationException::class);
});

it('ne remet pas le plafond d’alertes à zéro en désactivant puis réactivant les notifications', function () {
    matchingPreferences($this->helper, ['notify' => true]);
    foreach (range(1, 5) as $i) {
        matchingPublication($this->author, data: ['body' => 'Occasion '.$i]);
        if ($i < 5) {
            UserNotification::where('user_id', $this->helper->id)->update(['read_at' => now()]);
        }
    }
    matchingPreferences($this->helper, ['notify' => false]);
    $this->engine->profileChanged($this->helper->id);
    matchingPreferences($this->helper, ['notify' => true]);
    $this->engine->profileChanged($this->helper->id);
    matchingPublication($this->author, data: ['body' => 'Sixième occasion']);
    expect(UserNotification::where('kind', NotificationKind::MatchFound)->count())->toBe(5)->and(UserNotification::where('kind', NotificationKind::MatchFound)->whereNull('read_at')->count())->toBe(0);
});

it('présente une formation et des services rémunérés avec des libellés adaptés dans le formulaire et le Fil', function () {
    $page = Livewire::actingAs($this->author)->test(PublierPublication::class, ['slug' => 'aide'])->set('matching.enabled', true)->set('matching.activity', 'training')->set('matching.terms', 'paid');
    $page->assertSeeText('Proposer une formation')->set('body', 'Un atelier pour apprendre la couture.')->set('skills', ['Couture'])->set('matching.remote', true)->call('publish')->assertHasNoErrors();
    $post = Publication::sole();
    expect($post->displayLabel())->toBe('Je propose une formation');
    $this->actingAs($this->helper)->get(route('publications.show', $post))->assertOk()->assertSeeText('Je propose une formation');
    $service = matchingPublication($this->author, PublicationType::Offer, ['terms' => 'paid']);
    expect($service->displayLabel())->toBe('Je propose mes services');
});
