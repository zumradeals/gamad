<?php

use App\Enums\PublicationType;
use App\Livewire\Espace;
use App\Models\City;
use App\Models\IdentityVerification;
use App\Models\PersonConnection;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Services\People;
use App\Support\ProfileCompleteness;
use Livewire\Livewire;

/** Mon espace selon la maquette : trois colonnes, données réelles seulement. */
function espaceDe(User $qui)
{
    return Livewire::actingAs($qui)->test(Espace::class);
}

beforeEach(function () {
    $this->awa = person('Awa Traoré', ['Couture', 'Comptabilité', 'Tableur', 'Gestion'], ['Soudure'], null, 'public', [], 'Je coudrais pour toute la famille.', []);
});

it('renvoie les visiteurs vers la connexion', function () {
    auth()->logout();
    $this->get(route('espace'))->assertRedirect(route('login'));
});

it('affiche la navigation personnelle, les trois colonnes et les encadrés de la maquette', function () {
    espaceDe($this->awa)
        ->assertSeeInOrder(['Vue d’ensemble', 'Mon profil', 'Mes capacités', 'Mes réalisations', 'Mes publications', 'Mes relations', 'Paramètres'])
        ->assertSee('Visibilité du profil')->assertSee('Complétude du profil')->assertSee('Votre espace est privé.')
        ->assertSee('Actions rapides')->assertSee('Mon profil en un coup d’œil')->assertSee('Conseils et ressources')
        ->assertSee('Mes capacités')->assertSee('Mes réalisations récentes')->assertSee('Mes publications récentes');
});

it('montre la carte d’identité avec les seules informations enregistrées', function () {
    $this->awa->profile->forceFill(['headline' => 'Couturière de métier'])->save();

    espaceDe($this->awa)
        ->assertSee('Awa Traoré')->assertSee('Couturière de métier')->assertSee('Je coudrais pour toute la famille.')
        ->assertSee('Peut apporter')->assertSee('Couture')->assertSee('Comptabilité')->assertSee('Tableur')->assertSee('+1')
        ->assertSee('Voir mon profil public')->assertSee('Modifier mon profil')
        ->assertDontSee('Identité vérifiée par GAMAD')->assertDontSee('Disponible');
});

it('n’invente ni badge vérifié ni disponibilité', function () {
    $page = espaceDe($this->awa);
    $page->assertDontSee('Identité vérifiée par GAMAD')->assertDontSee('Disponible');

    IdentityVerification::query()->create(['user_id' => $this->awa->id, 'state' => 'verified', 'checked_name' => $this->awa->name, 'method' => 'in_person', 'verified_at' => now(), 'expires_at' => now()->addYear()]);
    $this->awa->profile->forceFill(['matching_preferences' => ['enabled' => true, 'available' => true, 'until' => null]])->save();

    espaceDe($this->awa)->assertSee('Identité vérifiée par GAMAD')->assertSee('Disponible');

    $this->awa->profile->forceFill(['matching_preferences' => ['enabled' => true, 'available' => true, 'until' => now()->subDay()->toDateString()]])->save();
    espaceDe($this->awa)->assertDontSee('Disponible');
});

it('affiche la localité enregistrée', function () {
    $ville = City::query()->with('country')->firstOrFail();
    $this->awa->profile->forceFill(['city_id' => $ville->id, 'country_id' => $ville->country_id])->save();

    espaceDe($this->awa)->assertSee($ville->label());
});

it('calcule les quatre indicateurs à partir des données réelles, réalisations et publications étant distinctes', function () {
    publication($this->awa, PublicationType::PersonalWork, ['body' => 'Un tablier cousu main']);
    publication($this->awa, PublicationType::PersonalWork, ['body' => 'Un cartable']);
    publication($this->awa, PublicationType::Need, ['body' => 'Besoin d’une machine']);
    $autre = person('Bob');
    publication($autre, PublicationType::PersonalWork, ['body' => 'La réalisation de Bob']);
    $people = app(People::class);
    $people->request($autre, $this->awa, 'Faisons connaissance.');
    $people->decide($this->awa, $autre, 'accept', PersonConnection::sole()->revision);

    espaceDe($this->awa)
        ->assertSeeHtmlInOrder(['data-testid="compteur-capacites"', '4', 'data-testid="compteur-realisations"', '2', 'data-testid="compteur-publications"', '1', 'data-testid="compteur-relations"', '1']);
});

it('ne compte pas une relation seulement demandée', function () {
    $autre = person('Bob');
    app(People::class)->request($autre, $this->awa, 'Faisons connaissance.');

    espaceDe($this->awa)->assertSeeHtml('data-testid="compteur-relations"')->assertSeeHtmlInOrder(['data-testid="compteur-relations"', '0']);
});

it('liste les réalisations et les publications séparément, sans celles des autres', function () {
    publication($this->awa, PublicationType::PersonalWork, ['body' => "Un tablier cousu main\nCoupé et assemblé en trois jours."]);
    publication($this->awa, PublicationType::Need, ['body' => 'Besoin d’une machine à coudre']);
    publication(person('Bob'), PublicationType::PersonalWork, ['body' => 'La réalisation secrète de Bob']);

    $page = espaceDe($this->awa);
    $page->assertSee('Un tablier cousu main')->assertSee('Coupé et assemblé en trois jours.')->assertSee('Besoin d’une machine à coudre')->assertDontSee('La réalisation secrète de Bob');

    $html = $page->html();
    $realisations = strpos($html, 'id="realisations"');
    $publications = strpos($html, 'id="publications"');
    expect(strpos($html, 'Un tablier cousu main'))->toBeGreaterThan($realisations)->toBeLessThan($publications)
        ->and(strpos($html, 'Besoin d’une machine à coudre'))->toBeGreaterThan($publications);
});

it('signale une réalisation masquée à son auteur', function () {
    $post = publication($this->awa, PublicationType::PersonalWork, ['body' => 'Une réalisation masquée']);
    $post->forceFill(['hidden_at' => now(), 'hidden_reason' => 'Test'])->save();

    espaceDe($this->awa)->assertSee('Une réalisation masquée')->assertSee('Masquée');
});

it('propose des états vides utiles pour les réalisations et les publications', function () {
    $nouveau = person('Nouveau');

    espaceDe($nouveau)->assertSee('Montrez ce que vous avez fait')->assertSee('Ajouter une réalisation')->assertSee('Votre première action commence ici')
        ->assertSee('Ajoutez vos savoir-faire pour que d’autres vous trouvent.')->assertDontSee('Peut apporter');
});

it('lie les actions rapides aux fonctionnalités existantes', function () {
    espaceDe($this->awa)
        ->assertSee(route('agir.publier', 'ma-realisation'), false)->assertSee(route('agir'), false)
        ->assertSee(route('espace.profil').'#offered-titre', false)->assertSee(route('personnes.show', $this->awa->profile->public_id), false);
});

it('calcule la complétude sur cinq critères réels, sans pourcentage inventé', function () {
    $nu = person('Nu');
    $c = ProfileCompleteness::for($nu, $nu->profile);
    expect($c['percent'])->toBe(0)->and($c['done'])->toBe(0)->and($c['next']['key'])->toBe('photo');

    $c = ProfileCompleteness::for($this->awa, $this->awa->profile);
    expect($c['percent'])->toBe(40)->and(collect($c['items'])->where('done', true)->pluck('key')->all())->toBe(['presentation', 'capacites'])->and($c['next']['key'])->toBe('photo');

    $this->awa->profile->forceFill(['photo_path' => 'photos/x.jpg', 'experiences' => [['title' => 'Atelier', 'kind' => 'professional', 'period' => '2020', 'description' => null]]])->save();
    publication($this->awa, PublicationType::PersonalWork);
    $c = ProfileCompleteness::for($this->awa->fresh(), $this->awa->profile->fresh());
    expect($c['percent'])->toBe(100)->and($c['next'])->toBeNull();
});

it('compte la présentation par la biographie ou le texte de présentation, et refuse les réalisations masquées', function () {
    $sans = person('Sans mot');
    expect(ProfileCompleteness::for($sans, $sans->profile)['items'][1]['done'])->toBeFalse();
    $sans->profile->forceFill(['introduction' => 'Je me présente.'])->save();
    expect(ProfileCompleteness::for($sans, $sans->profile->fresh())['items'][1]['done'])->toBeTrue();

    $post = publication($sans, PublicationType::PersonalWork);
    $post->forceFill(['hidden_at' => now(), 'hidden_reason' => 'Test'])->save();
    expect(ProfileCompleteness::for($sans, $sans->profile->fresh())['items'][4]['done'])->toBeFalse();
});

it('mène « Compléter mon profil » vers la première rubrique manquante', function () {
    $nu = person('Nu');
    espaceDe($nu)->assertSeeHtml('href="'.route('espace.profil').'"')->assertSee('Compléter mon profil')->assertSeeHtml('data-testid="completude"');

    espaceDe($this->awa)->assertSee('40 %');
    $nu->profile->forceFill(['bio' => 'Présentation', 'photo_path' => 'p.jpg'])->save();
    publication($nu, PublicationType::PersonalWork);
    $nu->profile->offeredSkills()->attach(Skill::findOrCreateByName('Menuiserie')->id, ['kind' => 'offered', 'position' => 0]);
    espaceDe($nu->fresh())->assertSeeHtml(route('espace.profil').'#profile-experiences');
});

it('affiche « profil complet » sans bouton quand tout est renseigné', function () {
    $this->awa->profile->forceFill(['photo_path' => 'photos/x.jpg', 'experiences' => [['title' => 'Atelier', 'kind' => 'professional', 'period' => '2020', 'description' => null]]])->save();
    publication($this->awa, PublicationType::PersonalWork);

    espaceDe($this->awa->fresh())->assertSee('Votre profil est complet.')->assertDontSee('Compléter mon profil')->assertSee('100 %');
});

it('change la visibilité du profil entre les deux règles réelles, pour soi seul', function () {
    $autre = person('Bob');
    espaceDe($this->awa)->assertSee('Public')->assertSee('Inscrits seulement')->assertSee('visible par tous')
        ->call('setVisibility', Profile::MEMBERS)->assertSee('n’est visible que des personnes inscrites');

    expect($this->awa->profile->fresh()->visibility)->toBe(Profile::MEMBERS)->and($autre->profile->fresh()->visibility)->toBe(Profile::PUBLIC);
    espaceDe($this->awa)->call('setVisibility', Profile::PUBLIC);
    expect($this->awa->profile->fresh()->visibility)->toBe(Profile::PUBLIC);
});

it('refuse une visibilité qui n’existe pas', function () {
    espaceDe($this->awa)->call('setVisibility', 'prive')->assertStatus(422);
    expect($this->awa->profile->fresh()->visibility)->toBe(Profile::PUBLIC);
});

it('ne suggère aucune personne quand personne ne convient', function () {
    espaceDe($this->awa)->assertDontSee('Suggestions pour vous');
    espaceDe(person('Nouveau'))->assertDontSee('Suggestions pour vous');
});

it('suggère de vraies personnes dont les capacités complètent les miennes, sans moi ni mes relations', function () {
    $soudeur = person('Moussa Soudeur', ['Soudure']);
    $apprenti = person('Binta Apprentie', [], ['Couture']);
    $sansRapport = person('Hors sujet', ['Cuisine']);
    $relation = person('Déjà relation', ['Soudure']);
    $prive = person('Profil fermé', ['Soudure'], [], null, 'members'); // visible des inscrits : Awa l'est
    app(People::class)->request($this->awa, $relation, 'Bonjour.');

    $page = espaceDe($this->awa);
    $page->assertSee('Suggestions pour vous')->assertSee(route('personnes.show', $soudeur->profile->public_id), false);

    // La liste des suggestions, sans le reste de la page (« Mes liens » nomme la demande envoyée).
    $html = $page->html();
    $bloc = substr($html, strpos($html, 'id="suggestions-titre-droite"'), strpos($html, 'id="conseils"') - strpos($html, 'id="suggestions-titre-droite"'));
    expect($bloc)->toContain('Moussa Soudeur')->toContain('Binta Apprentie')->toContain('Profil fermé')
        ->not->toContain('Hors sujet')->not->toContain('Déjà relation')->not->toContain('Awa Traoré');
    expect($sansRapport)->not->toBeNull();
});

it('respecte la visibilité des profils suggérés', function () {
    $ferme = person('Profil fermé', ['Soudure'], [], null, 'members');
    $visiteur = person('Moussa Soudeur', ['Soudure']);
    $visiteur->forceFill(['email_verified_at' => null])->save();

    espaceDe($this->awa)->assertSee('Profil fermé');
    expect($ferme)->not->toBeNull();
});

it('garde l’accès à toutes les fonctions existantes de Mon espace, chacune dans sa rubrique', function () {
    $contenus = [
        'echanges' => 'Mes échanges', 'enregistrements' => 'Mes enregistrements', 'attestations' => 'Mes attestations', 'entreprise' => 'Mon entreprise sur GAMAD',
        'pistes' => 'Des pistes pour avancer', 'relations' => 'Mes liens', 'compte' => 'Nom affiché',
    ];

    foreach ($contenus as $rubrique => $texte) {
        espaceDe($this->awa)->call('show', $rubrique)->assertSet('section', $rubrique)->assertSee($texte);
    }

    espaceDe($this->awa)->call('show', 'compte')->assertSee('Suppression du compte')->assertSee('Se déconnecter');
});

it('garde la vue d’ensemble courte : seulement identité, indicateurs, capacités, réalisations et publications', function () {
    $page = espaceDe($this->awa)->assertSet('section', 'vue-ensemble')
        ->assertSee('Mes capacités')->assertSee('Mes réalisations récentes')->assertSee('Mes publications récentes')
        ->assertDontSee('Suppression du compte')->assertDontSee('Nom affiché')->assertDontSee('Retrouvez les publications que vous avez conservées')
        ->assertDontSee('Créez votre page entreprise')->assertDontSee('Des pistes pour avancer')->assertDontSee('Faire vérifier mon identité')->assertDontSee('Vos relations et suivis sont privés');
    expect(substr_count($page->html(), '<section'))->toBeLessThan(12);
});

it('n’affiche « À faire maintenant » que lorsqu’une action réelle attend la personne', function () {
    espaceDe($this->awa)->assertDontSee('À faire maintenant');

    $besoin = publication($this->awa, PublicationType::Need, ['body' => 'Un besoin avec une réponse']);
    geste(person('Aidante', ['Couture']), $besoin);

    espaceDe($this->awa)->assertSee('À faire maintenant')->assertSee('1 réponse à traiter');
});

it('met en évidence la rubrique active et propose le retour à la vue d’ensemble', function () {
    espaceDe($this->awa)->assertSeeHtml('aria-current="page"')
        ->call('show', 'compte')->assertSet('section', 'compte')->assertSee('Vue d’ensemble')->assertSeeHtml('wire:click.prevent="show(\'vue-ensemble\')"')
        ->call('show', 'vue-ensemble')->assertSet('section', 'vue-ensemble');
});

it('ouvre une rubrique par son adresse et ignore une rubrique inconnue', function () {
    $this->actingAs($this->awa)->get(route('espace', ['rubrique' => 'compte']))->assertOk()->assertSee('Nom affiché');
    $this->actingAs($this->awa)->get(route('espace', ['rubrique' => 'inconnue']))->assertOk()->assertSee('Mes réalisations récentes')->assertDontSee('Nom affiché');
    espaceDe($this->awa)->call('show', 'inconnue')->assertSet('section', 'vue-ensemble');
});

it('liste toutes les réalisations et publications dans leur rubrique, au-delà de l’aperçu', function () {
    foreach (range(1, 5) as $i) {
        publication($this->awa, PublicationType::PersonalWork, ['body' => 'Réalisation numéro '.$i]);
    }
    foreach (range(1, 7) as $i) {
        publication($this->awa, PublicationType::Need, ['body' => 'Publication numéro '.$i]);
    }

    $apercu = espaceDe($this->awa);
    expect(substr_count($apercu->html(), 'Réalisation numéro'))->toBe(3)->and(substr_count($apercu->html(), 'Publication numéro'))->toBe(5);
    $apercu->assertSee('Voir tout');

    espaceDe($this->awa)->call('show', 'realisations')->assertSee('Réalisation numéro 1')->assertSee('Réalisation numéro 5')->assertSee('Mes réalisations');
    espaceDe($this->awa)->call('show', 'publications')->assertSee('Publication numéro 1')->assertSee('Publication numéro 7');
});

it('réserve la modération et l’administration à ceux qui y ont droit, aussi par l’adresse et côté serveur', function () {
    espaceDe($this->awa)->assertDontSee('Modération')->assertDontSee('Administration')
        ->call('show', 'moderation')->assertSet('section', 'vue-ensemble')->call('show', 'administration')->assertSet('section', 'vue-ensemble');
    $this->actingAs($this->awa)->get(route('espace', ['rubrique' => 'administration']))->assertDontSee('Courriel, nom et adresse de la plateforme');

    $moderateur = User::factory()->moderator()->create();
    espaceDe($moderateur)->assertSee('Modération')->assertDontSee('Administration')
        ->call('show', 'moderation')->assertSet('section', 'moderation')->assertSee('Ouvrir la file de modération')
        ->call('show', 'administration')->assertSet('section', 'vue-ensemble');

    espaceDe(User::factory()->superAdmin()->create())->assertSee('Modération')->assertSee('Administration')
        ->call('show', 'administration')->assertSet('section', 'administration')->assertSee('Paramètres');
});

it('refuse de forcer une rubrique interdite en modifiant l’état du composant', function () {
    espaceDe($this->awa)->set('section', 'administration')->assertSet('section', 'vue-ensemble')->assertDontSee('Courriel, nom et adresse de la plateforme');
});

it('ne montre jamais de chiffre ni de personne de démonstration', function () {
    $nouveau = person('Nouveau');

    espaceDe($nouveau)->assertDontSee('70 %')->assertDontSee('Saliou Koné')->assertDontSee('Aïssata Touré')->assertDontSee('Daouda Coulibaly')->assertSee('0 %');
});

it('ne montre ni l’adresse e-mail ni le téléphone des autres dans les suggestions', function () {
    $autre = person('Moussa Soudeur', ['Soudure'], [], null, 'public', [], null, ['email' => 'moussa-prive@example.com']);

    espaceDe($this->awa)->assertSee('Moussa Soudeur')->assertDontSee('moussa-prive@example.com');
});
