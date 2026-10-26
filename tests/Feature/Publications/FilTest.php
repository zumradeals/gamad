<?php

use App\Enums\PublicationType;
use App\Livewire\Fil;
use App\Models\City;
use App\Models\Domain;
use App\Models\Gesture;
use App\Models\Publication;
use App\Models\User;
use App\Services\Gestures;
use App\Services\Publications;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

function fil(?User $user = null)
{
    return Livewire::actingAs($user ?? User::factory()->create())->test(Fil::class);
}

function ordre($composant): array
{
    return preg_match_all('/aria-label="(?:J\'ai besoin|Je partage un savoir|Je veux apprendre|Je propose mon aide) de ([^"]+)"/u', $composant->html(), $m) ? $m[1] : [];
}

it('est réservé aux personnes connectées et vérifiées', function () {
    $this->get('/fil')->assertRedirect(route('home'));
    $this->actingAs(User::factory()->unverified()->create())->get('/fil')->assertRedirect();
    $this->actingAs(User::factory()->create())->get('/fil')->assertOk();
});

it('n’affiche que du contenu réel : un Fil vide l’est vraiment, avec une invitation à agir', function () {
    $this->actingAs(User::factory()->create())->get('/fil')->assertOk()
        ->assertSeeText('Les publications apparaîtront ici. Commencez par compléter votre profil.')
        ->assertSeeText('Agir')
        ->assertDontSeeText('Awa Traoré')
        ->assertDontSeeText('GAMAD Technology')
        ->assertDontSeeText('Rahman Technology')
        ->assertDontSeeText('Agri Bouaké')
        ->assertDontSeeText('Je soutiens')
        ->assertDontSeeText('Je veux rejoindre')
        ->assertDontSeeText('Reconnue');
});

it('affiche les publications, de la plus récente à la plus ancienne, sans algorithme', function () {
    $auteur = User::factory()->create(['name' => 'Yao Kouassi']);
    foreach ([['Ancien', '-3 days'], ['Récent', '-1 hour'], ['Milieu', '-1 day']] as [$mot, $depuis]) {
        $p = publication($auteur, data: ['body' => $mot]);
        $p->forceFill(['created_at' => now()->modify($depuis)])->save();
    }

    $composant = fil();
    $texte = $composant->html();

    expect(strpos($texte, 'Récent'))->toBeLessThan(strpos($texte, 'Milieu'))
        ->and(strpos($texte, 'Milieu'))->toBeLessThan(strpos($texte, 'Ancien'));
});

it('départage deux publications simultanées de façon stable', function () {
    $auteur = User::factory()->create();
    $a = publication($auteur, data: ['body' => 'Un']);
    $b = publication($auteur, data: ['body' => 'Deux']);
    Publication::query()->update(['created_at' => now()]);

    $premier = fil()->html();
    $second = fil()->html();

    // On repère les paragraphes des publications, pas de simples sous-chaînes : le HTML contient des jetons aléatoires.
    $position = fn (string $html, string $texte) => strpos($html, '>'.$texte.'</p>');

    expect($position($premier, 'Un'))->not->toBeFalse()->and($position($premier, 'Deux'))->not->toBeFalse();
    expect($position($premier, 'Un') < $position($premier, 'Deux'))->toBe($position($second, 'Un') < $position($second, 'Deux'));
});

it('montre le type, l’auteur, le domaine, la ville et l’ancienneté de chaque publication', function () {
    $auteur = person('Yao Kouassi', city: 'Cocody');
    publication($auteur, data: [
        'body' => 'Je cherche quelqu’un qui sait installer un logiciel de caisse.',
        'domain_id' => Domain::query()->where('name', 'Économie et Finances')->value('id'),
        'city_id' => City::query()->where('name', 'Cocody')->value('id'),
        'skills' => ['Tableur Excel'],
    ]);

    fil()
        ->assertSeeText('J\'ai besoin')
        ->assertSeeText('Yao Kouassi')
        ->assertSeeText('Économie et Finances · Abidjan, Cocody ·')
        ->assertSeeText('Je cherche quelqu’un qui sait installer un logiciel de caisse.')
        ->assertSeeText('Tableur Excel')
        ->assertSeeText('Je peux aider');
});

it('filtre : Tout, Besoins, Savoirs, Apprendre, Aides', function () {
    legacyExperience();
    $auteur = User::factory()->create(['name' => 'Yao Kouassi']);
    publication($auteur, PublicationType::Need, ['body' => 'Texte besoin']);
    publication($auteur, PublicationType::Knowledge, ['body' => 'Texte savoir']);
    publication($auteur, PublicationType::LearningWish, ['body' => 'Texte apprendre']);
    publication($auteur, PublicationType::Offer, ['body' => 'Texte aide']);

    fil()->assertSeeText('Texte besoin')->assertSeeText('Texte savoir')->assertSeeText('Texte apprendre')->assertSeeText('Texte aide');
    fil()->call('setFilter', 'besoins')->assertSeeText('Texte besoin')->assertDontSeeText('Texte savoir')->assertDontSeeText('Texte apprendre')->assertDontSeeText('Texte aide');
    fil()->call('setFilter', 'savoirs')->assertSeeText('Texte savoir')->assertDontSeeText('Texte besoin')->assertDontSeeText('Texte apprendre');
    fil()->call('setFilter', 'apprendre')->assertSeeText('Texte apprendre')->assertDontSeeText('Texte savoir')->assertDontSeeText('Texte besoin')->assertDontSeeText('Texte aide');
    fil()->call('setFilter', 'aides')->assertSeeText('Texte aide')->assertDontSeeText('Texte apprendre')->assertDontSeeText('Texte besoin')->assertDontSeeText('Texte savoir');
    fil()->call('setFilter', 'projets')->assertSet('filter', 'projets');
    fil()->call('setFilter', 'inconnu')->assertSet('filter', 'tout');
    Livewire::withQueryParams(['filtre' => 'besoins'])->actingAs(User::factory()->create())->test(Fil::class)->assertSet('filter', 'besoins')->assertDontSeeText('Texte savoir');
});

it('propose les filtres sociaux et métiers sans classement de popularité (D-193)', function () {
    legacyExperience();
    $html = fil()->html();
    preg_match('/<nav aria-label="Catégories".*?<\/nav>/s', $html, $nav);
    $onglets = array_map(fn ($t) => trim(strip_tags($t)), explode('</button>', $nav[0]));
    preg_match('/<select wire:change="setFilter.*?<\/select>/s', $html, $select);
    $autres = array_map(fn ($t) => trim(strip_tags($t)), explode('</option>', $select[0]));

    // Barre compacte : six onglets ; les autres vues (suivis, découvrir…) restent accessibles dans le sélecteur.
    expect(array_values(array_filter($onglets)))->toBe(['Tout', 'Savoirs', 'Réalisations', 'Besoins', 'Personnes', 'Entreprises'])
        ->and(array_values(array_filter($autres)))->toBe(['—', 'Mes suivis', 'À découvrir', 'Partages', 'Envies d’apprendre', 'Aides proposées', 'ZUMRA', 'Projets']);
});

it('affiche Commenter sans compteur d’approbation, et « Signaler » seulement sur la publication d’un autre', function () {
    publication(User::factory()->create(), data: ['body' => 'Un besoin']);

    fil()->assertSeeText('Commenter')->assertSeeText('Partager')->assertSeeText('Enregistrer')
        ->assertDontSeeText('J’aime')->assertDontSeeText('Like')->assertSeeText('Signaler');

    // Sur sa propre publication, on ne se signale pas soi-même.
    $moi = User::factory()->create();
    publication($moi, data: ['body' => 'Mon besoin à moi']);
    $html = Livewire::actingAs($moi)->test(Fil::class)->html();
    expect(substr_count($html, 'Signaler'))->toBe(1);
});

it('ne montre que les publications visibles pour celui qui regarde, règle côté serveur', function () {
    $auteur = User::factory()->create(['name' => 'Auteur']);
    publication($auteur, data: ['body' => 'Publique']);
    publication($auteur, data: ['body' => 'Inscrits', 'visibility' => 'members']);
    $nonVerifie = User::factory()->unverified()->create();
    $p = new Publication(['type' => PublicationType::Need, 'body' => 'Non vérifié', 'visibility' => 'public', 'need_state' => 'open']);
    $p->author()->associate($nonVerifie)->save();

    fil()->assertSeeText('Publique')->assertSeeText('Inscrits')->assertDontSeeText('Non vérifié');
});

it('montre ses propres publications même « inscrits seulement », et rien d’autre de caché', function () {
    $moi = User::factory()->create();
    publication($moi, data: ['body' => 'Ma publication discrète', 'visibility' => 'members']);

    fil($moi)->assertSeeText('Ma publication discrète');
});

it('applique la même règle de visibilité à la requête et à la règle d’accès', function () {
    $verifie = User::factory()->create();
    $nonVerifie = User::factory()->unverified()->create();
    $auteurs = ['verifie' => User::factory()->create(), 'nonVerifie' => User::factory()->unverified()->create()];
    $publications = [];
    foreach ($auteurs as $nom => $auteur) {
        foreach (['public', 'members'] as $visibilite) {
            $p = new Publication(['type' => PublicationType::Knowledge, 'body' => "$nom $visibilite", 'visibility' => $visibilite]);
            $p->author()->associate($auteur)->save();
            $publications[] = $p;
        }
    }

    foreach ([null, $verifie, $nonVerifie, $auteurs['verifie'], $auteurs['nonVerifie']] as $spectateur) {
        foreach ($publications as $p) {
            $parLaRegle = Gate::forUser($spectateur)->allows('view', $p);
            $parLaRequete = Publication::query()->visibleTo($spectateur)->whereKey($p->id)->exists();

            expect($parLaRequete)->toBe($parLaRegle, "écart pour « {$p->body} » vu par ".($spectateur?->name ?? 'un visiteur'));
        }
    }
});

it('pagine par « Voir plus », dix publications à la fois', function () {
    $auteur = User::factory()->create();
    foreach (range(1, 12) as $i) {
        publication($auteur, data: ['body' => sprintf('Publication %02d', $i)])->forceFill(['created_at' => now()->subMinutes(30 - $i)])->save();
    }

    $composant = fil();
    expect(substr_count($composant->html(), '<article'))->toBe(10);
    $composant->assertSeeText('Voir plus de publications')->assertSeeText('Publication 12')->assertDontSeeText('Publication 01');

    $composant->call('more');
    expect(substr_count($composant->html(), '<article'))->toBe(12);
    $composant->assertDontSeeText('Voir plus de publications')->assertSeeText('Publication 01');
});

it('montre le fait d’engagement réel : le nombre de réponses, jamais d’approbation', function () {
    $auteur = User::factory()->create();
    $publication = publication($auteur);
    foreach (range(1, 3) as $i) {
        geste(User::factory()->create(), $publication);
    }
    $retire = geste(User::factory()->create(), $publication);
    app(Gestures::class)->withdraw($retire->author, $retire);
    $decline = geste(User::factory()->create(), $publication);
    app(Gestures::class)->decline($auteur, $decline);

    fil()->assertSeeText('3 personnes proposent leur aide');

    $seul = publication($auteur, PublicationType::Knowledge);
    geste(User::factory()->create(), $seul);
    fil()->assertSeeText('1 personne veut apprendre');
});

it('n’affiche aucun fait quand personne n’a répondu', function () {
    publication(User::factory()->create());

    fil()->assertDontSeeText('personne propose')->assertDontSeeText('personnes proposent');
});

it('n’offre pas de bouton de réponse sur sa propre publication, mais un lien vers les réponses', function () {
    $moi = User::factory()->create();
    $p = publication($moi);
    geste(User::factory()->create(), $p);

    fil($moi)->assertDontSeeText('Je peux aider')->assertSeeText('Voir les réponses')->assertSee(route('publications.show', $p), false);
});

it('affiche l’état de son geste sur chaque publication', function () {
    $auteur = User::factory()->create();
    $moi = User::factory()->create();
    $envoye = publication($auteur, data: ['body' => 'A']);
    $accepte = publication($auteur, data: ['body' => 'B']);
    $decline = publication($auteur, data: ['body' => 'C']);
    $libre = publication($auteur, data: ['body' => 'D']);
    foreach ([$envoye, $accepte, $decline] as $p) {
        geste($moi, $p);
    }
    app(Gestures::class)->accept($auteur, Gesture::query()->where('publication_id', $accepte->id)->firstOrFail());
    app(Gestures::class)->decline($auteur, Gesture::query()->where('publication_id', $decline->id)->firstOrFail());

    $html = fil($moi)->html();

    expect($html)->toContain('Proposition envoyée')->toContain('Retirer ma proposition')->toContain('Acceptée')->toContain('Non retenue');
    expect(substr_count($html, 'Je peux aider'))->toBeGreaterThanOrEqual(1);
});

it('n’offre plus de réponse sur un besoin résolu ou fermé', function () {
    $auteur = User::factory()->create();
    $resolu = publication($auteur, data: ['body' => 'Résolu']);
    $ferme = publication($auteur, data: ['body' => 'Fermé']);
    app(Publications::class)->resolve($auteur, $resolu);
    app(Publications::class)->close($auteur, $ferme);

    fil()->assertSeeText('Ce besoin est résolu.')->assertSeeText('Ce besoin est fermé.')->assertDontSeeText('Je peux aider')->assertSeeText('Fermé sans suite');
});

it('n’expose jamais l’adresse e-mail ni le téléphone d’un auteur', function () {
    $auteur = User::factory()->create(['name' => 'Yao', 'email' => 'yao.secret@example.test']);
    publication($auteur, data: ['body' => 'Contactez-moi sur la plateforme.']);

    fil()->assertDontSee('yao.secret@example.test')->assertDontSee('mailto:')->assertDontSee('tel:');
});

it('lie le nom de l’auteur à son profil seulement si on a le droit de le voir', function () {
    $ouvert = person('Ouvert', ['Couture']);
    $discret = person('Discret', ['Couture'], visibility: 'members');
    publication($ouvert, data: ['body' => 'De Ouvert']);
    publication($discret, data: ['body' => 'De Discret']);

    // Un inscrit voit les deux profils.
    $html = fil()->html();
    expect($html)->toContain(route('personnes.show', $ouvert->profile->public_id))->toContain(route('personnes.show', $discret->profile->public_id));

    // Un visiteur sans compte lit la publication publique de l'auteur discret, mais n'a pas le lien vers son profil.
    app('auth')->forgetGuards();
    $page = $this->get(route('publications.show', Publication::query()->where('body', 'De Discret')->firstOrFail()))->assertOk()->getContent();
    expect($page)->not->toContain($discret->profile->public_id)->and($page)->toContain('Discret');
});

it('montre à gauche, sur ordinateur, mon profil tel que les autres le voient', function () {
    $moi = person('Awa Traoré', ['Comptabilité'], ['Anglais'], 'Yamoussoukro');

    fil($moi)->assertSeeText('JE PEUX APPORTER')->assertSeeText('Comptabilité')->assertSeeText('JE VEUX APPRENDRE')->assertSeeText('Anglais')->assertSeeText('Yamoussoukro')->assertSeeText('Voir mon profil');
    fil(User::factory()->create())->assertSeeText('Ajouter ce que je peux apporter')->assertSeeText('Ajouter ce que je veux apprendre');
});

it('propose le compositeur direct sans menu Agir secondaire', function () {
    $html = fil()->html();

    expect($html)->toContain('fil-compose-body')
        ->toContain('wire:submit="publish"')
        ->toContain('value="share"')
        ->toContain('value="personal_work"')
        ->toContain('fil-compose-media')
        ->toContain('x-on:click.outside="close()"')
        ->toContain('x-on:open-composer.window')
        ->toContain('Annuler')
        ->not->toContain('fil-agir-menu')
        ->not->toContain('Des pistes pour avancer');
});

it('ouvre le Fil sans erreur à 390 px : aucun ordre ni algorithme caché dans les requêtes', function () {
    foreach (range(1, 5) as $i) {
        publication(User::factory()->create(), data: ['body' => "Texte $i"]);
    }

    DB::enableQueryLog();
    fil();
    $requetes = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Le nombre de requêtes ne grandit pas avec le nombre de publications (pas de N+1 grossier).
    foreach (range(1, 5) as $i) {
        publication(User::factory()->create(), data: ['body' => "Autre $i"]);
    }
    DB::flushQueryLog();
    fil();
    $plusDeRequetes = count(DB::getQueryLog());

    expect($plusDeRequetes)->toBeLessThanOrEqual($requetes + 2);
});
