<?php

use App\Enums\LedgerKind;
use App\Enums\PaymentStatus;
use App\Enums\PublicationType;
use App\Exceptions\ContributionException;
use App\Exceptions\PaymentException;
use App\Livewire\CarrefourPersonnes;
use App\Livewire\Espace;
use App\Livewire\Fil;
use App\Livewire\PageContribution;
use App\Livewire\PagePortefeuille;
use App\Livewire\PublierPublication;
use App\Models\Contribution;
use App\Models\Conversation;
use App\Models\Payment;
use App\Models\Publication;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Zumra;
use App\Services\Contributions;
use App\Services\Gestures;
use App\Services\Invitations;
use App\Services\JoinRequests;
use App\Services\Payments;
use App\Services\Projects;
use App\Services\Publications;
use App\Services\Todo;
use App\Services\Zumras;
use App\Support\Experience;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** Prépare une activité antérieure par les services réels, puis rétablit le parcours initial. */
function avantRecentrage(callable $prepare): mixed
{
    config(['gamad.initial_experience' => false]);

    try {
        return $prepare();
    } finally {
        config(['gamad.initial_experience' => true]);
    }
}

beforeEach(function () {
    config(['gamad.initial_experience' => true]);
});

it('active le parcours initial par défaut dans la configuration livrée', function () {
    $defaults = require config_path('gamad.php');
    expect($defaults['initial_experience'])->toBeTrue()->and(Experience::initial())->toBeTrue();
});

it('propose Accueil Personnes Entreprises Mon espace et Agir, sans fenêtre de découverte ni modules gelés', function () {
    $user = person('Nouvelle personne', ['Couture']);

    foreach (['fil', 'personnes.index', 'agir', 'espace'] as $route) {
        $html = $this->actingAs($user)->get(route($route))->assertOk()->getContent();
        preg_match_all('/<nav aria-label="Navigation principale".*?<\/nav>/s', $html, $navs);
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(implode(' ', $navs[0]))));
        expect($text)->toContain('Accueil')->toContain('Personnes')->toContain('Entreprises')->toContain('Agir')->toContain('Mon espace')
            ->toContain('Découvrir')->not->toContain('ZUMRA')->not->toContain('Projets')->not->toContain('Besoins');
        expect($html)->not->toContain('aria-labelledby="decouvrir-titre"')
            ->not->toContain('href="'.route('zumras.index').'"')->not->toContain('href="'.route('projets.index').'"')
            ->not->toContain('href="'.route('besoins.index').'"')->not->toContain('href="'.route('contribution').'"');
    }

    $this->get(route('espace'))->assertDontSeeText('Mes activités antérieures')->assertSeeText('Mes échanges');
    expect(Wallet::query()->count())->toBe(0);
});

it('retire de l’accueil les promesses et exemples des modules en pause en gardant le héros', function () {
    avantRecentrage(function () {
        $founder = person('Fondatrice', ['Couture']);
        $zumra = zumra($founder, 'Collectif antérieur');
        projet($founder, $zumra, 'Projet antérieur');
    });

    $this->get('/')->assertOk()->assertSeeText('Rencontrez des personnes')->assertSeeText('Exprimez un besoin')
        ->assertSee('images/gamad-accueil-hero.webp', false)->assertDontSeeText('Collectif antérieur')
        ->assertDontSeeText('Projet antérieur')->assertDontSeeText('Trouvez votre ZUMRA')->assertDontSeeText('Faites avancer un projet')
        ->assertDontSee('href="'.route('zumras.index').'"', false)->assertDontSee('href="'.route('projets.index').'"', false);
});

it('redirige les anciens carrefours vers le parcours actif sans perdre les détails', function () {
    foreach (['decouvrir', 'zumras.index', 'projets.index'] as $route) {
        $this->get(route($route))->assertRedirect(route('personnes.index'));
    }
    $this->get(route('besoins.index'))->assertRedirect(route('fil', ['filtre' => 'besoins']));
    $this->get(route('fil', ['filtre' => 'besoins']))->assertRedirect(route('home'));
});

it('garde les besoins dans le Fil avec leurs réponses et une conversation retrouvable après lecture', function () {
    $author = person('Auteur du besoin', ['Couture']);
    $helper = person('Personne aidante', ['Comptabilité']);
    $post = publication($author, PublicationType::Need, ['body' => 'Un besoin personnel à accompagner.']);
    publication($author, PublicationType::Knowledge, ['body' => 'Un savoir personnel.']);

    $this->actingAs($helper)->get(route('fil', ['filtre' => 'besoins']))->assertOk()
        ->assertSeeText($post->body)->assertDontSeeText('Un savoir personnel.');
    $gesture = geste($helper, $post);
    app(Gestures::class)->accept($author, $gesture);
    $conversation = $gesture->fresh()->conversation;
    ecrire($helper, $conversation, 'Nous pouvons avancer ensemble.');
    pageConversation($author, $conversation)->assertSeeText('Nous pouvons avancer ensemble.');

    $this->actingAs($author)->get(route('espace', ['rubrique' => 'echanges']))->assertOk()
        ->assertSee('href="'.route('conversations.show', $conversation).'"', false)->assertSeeText('Personne aidante');
    $this->actingAs(User::factory()->create())->get(route('conversations.show', $conversation))->assertNotFound();
});

it('conserve les quatre publications personnelles et la recherche de capacités', function () {
    $user = person('Awa', ['Couture'], ['Comptabilité']);
    foreach (PublicationType::forPerson() as $type) {
        Livewire::actingAs($user)->test(PublierPublication::class, ['slug' => $type->slug()])
            ->set('body', 'Une publication personnelle : '.$type->value)->call('publish')->assertHasNoErrors();
    }
    expect(Publication::query()->where('author_id', $user->id)->count())->toBe(6);
    Livewire::test(CarrefourPersonnes::class)->set('query', 'couture')->assertSeeText('Awa');
});

it('ne traite pas les filtres des modules gelés comme des filtres actifs', function () {
    $user = person('Awa', ['Couture']);
    foreach (['zumra', 'projets'] as $filter) {
        Livewire::actingAs($user)->test(Fil::class)->call('setFilter', $filter)->assertSet('filter', 'tout');
    }
});

it('bloque la création de ZUMRA par le service et par une ancienne URL', function () {
    $user = person('Fondatrice', ['Couture']);
    $this->actingAs($user)->get(route('zumras.create'))->assertForbidden();
    expect(fn () => app(Zumras::class)->create($user, []))->toThrow(AuthorizationException::class);
    expect(Zumra::query()->count())->toBe(0);
});

it('bloque les nouvelles créations et publications collectives tout en gardant les données antérieures', function () {
    $founder = person('Fondatrice', ['Couture']);
    [$zumra, $project, $post] = avantRecentrage(function () use ($founder) {
        $zumra = zumra($founder);
        $project = projet($founder, $zumra);
        $post = app(Publications::class)->publish($founder, PublicationType::Need, ['body' => 'Besoin collectif antérieur.'], $zumra);

        return [$zumra, $project, $post];
    });

    expect(fn () => app(Projects::class)->create($founder, $zumra, []))->toThrow(AuthorizationException::class);
    expect(fn () => app(Publications::class)->publish($founder, PublicationType::Need, ['body' => 'Nouveau'], $zumra))->toThrow(ValidationException::class);
    expect(fn () => app(Publications::class)->publish($founder, PublicationType::Need, ['body' => 'Nouveau'], project: $project))->toThrow(ValidationException::class);
    $this->actingAs($founder)->get(route('projets.create', $zumra))->assertForbidden();
    $this->get(route('zumras.publier', [$zumra, 'besoin']))->assertForbidden();
    $this->get(route('projets.publier', [$project, 'besoin']))->assertForbidden();
    $this->get(route('zumras.show', $zumra))->assertOk()->assertDontSeeText('Partager un savoir')->assertDontSeeText('Inviter des personnes selon leurs capacités');
    $this->get(route('projets.show', $project))->assertOk();
    $this->get(route('publications.show', $post))->assertOk()->assertSeeText($post->body);
    $this->get(route('espace', ['rubrique' => 'activites']))->assertSeeText('Mes activités antérieures')->assertSee('href="'.route('zumras.show', $zumra).'"', false);
    expect($zumra->fresh())->not->toBeNull()->and($project->fresh())->not->toBeNull()->and($post->fresh()->zumra_id)->toBe($zumra->id);
});

it('refuse aussi un envoi depuis un formulaire collectif ouvert avant le gel', function () {
    $founder = person('Fondatrice', ['Couture']);
    $zumra = avantRecentrage(fn () => zumra($founder));
    $form = avantRecentrage(fn () => Livewire::actingAs($founder)->test(PublierPublication::class, ['zumra' => $zumra, 'slug' => 'besoin']));
    $form->set('body', 'Envoi après le recentrage')->call('publish')->assertHasErrors('body');
    expect(Publication::query()->count())->toBe(0);
});

it('bloque les demandes et invitations nouvelles mais permet de traiter une demande déjà engagée', function () {
    $founder = person('Fondatrice', ['Couture']);
    $member = person('Demande existante', ['Couture']);
    $newcomer = person('Nouveau', ['Couture']);
    [$zumra, $pending] = avantRecentrage(function () use ($founder, $member) {
        $zumra = zumra($founder);

        return [$zumra, app(JoinRequests::class)->propose($member, $zumra)];
    });
    expect(fn () => app(JoinRequests::class)->propose($newcomer, $zumra))->toThrow(AuthorizationException::class);
    expect(fn () => app(Invitations::class)->invite($founder, $zumra, $newcomer))->toThrow(AuthorizationException::class);
    app(JoinRequests::class)->accept($founder, $pending);
    expect($zumra->fresh()->roleOf($member))->not->toBeNull();
    $this->actingAs($newcomer)->get(route('zumras.show', $zumra))->assertNotFound();
    $this->actingAs($founder)->get(route('personnes.show', $newcomer->profile->public_id))->assertOk()->assertDontSeeText('Inviter dans');
});

it('ne crée pas de portefeuille depuis une ancienne URL pour un compte neuf', function () {
    ouvrirPortefeuilles();
    $user = person('Nouveau', ['Couture']);
    $this->actingAs($user)->get(route('portefeuille'))->assertNotFound();
    $this->get(route('contribution'))->assertNotFound();
    $this->get(route('espace'))->assertOk()->assertDontSeeText('Mes activités antérieures')->assertDontSeeText('Mon portefeuille');
    expect(Wallet::query()->count())->toBe(0);
});

it('conserve les relevés privés sans proposer ni autoriser de nouvelles opérations financières', function () {
    $user = person('Titulaire', ['Couture']);
    $wallet = avantRecentrage(function () use ($user) {
        configurerPaiement();
        $wallet = portefeuilles()->forPerson($user);
        portefeuilles()->post($wallet, LedgerKind::Recharge, 2000, 'historique');
        $wallet->forceFill(['monthly_reminder' => true])->save();

        return $wallet;
    });
    Http::fake();
    expect(app(Payments::class)->available())->toBeFalse();
    expect(fn () => lancer($user, 500, $wallet))->toThrow(PaymentException::class);
    expect(fn () => app(Contributions::class)->fromWallet($user, $wallet, 500))->toThrow(ContributionException::class);
    Livewire::actingAs($user)->test(PagePortefeuille::class)->assertSeeText('2 000 ZAHAB')->assertDontSee('wire:submit="recharge"', false);
    Livewire::actingAs($user)->test(PageContribution::class)->assertDontSee('wire:submit="contribute"', false)->assertSeeText('Historique');
    $this->actingAs($user)->get(route('espace', ['rubrique' => 'activites']))->assertSeeText('Mon historique de portefeuille');
    expect(collect(app(Todo::class)->for($user))->pluck('key')->filter(fn ($key) => str_starts_with($key, 'contribution-')))->toBeEmpty();
    expect(solde($wallet))->toBe(2000)->and(Payment::query()->count())->toBe(0);
    Http::assertNothingSent();
    $this->actingAs(User::factory()->create())->get(route('zumras.portefeuille', '00000000-0000-4000-8000-000000000001'))->assertNotFound();
});

it('continue de confirmer une seule fois les paiements engagés avant le gel', function (string $purpose) {
    $user = person('Titulaire', ['Couture']);
    $payment = avantRecentrage(function () use ($user, $purpose) {
        configurerPaiement();
        prestataire();
        $wallet = portefeuilles()->forPerson($user);

        return app(Payments::class)->start($user, $wallet, 1000, route('portefeuille'), route('portefeuille'), $purpose);
    });
    prestataire([$payment->provider_reference => ['status' => 'completed']]);
    webhook($payment)->assertOk();
    webhook($payment)->assertOk();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Completed);
    if ($purpose === 'recharge') {
        expect(solde($payment->wallet))->toBe(1000)->and($payment->wallet->entries()->count())->toBe(1);
    } else {
        expect(Contribution::query()->where('wallet_id', $payment->wallet_id)->count())->toBe(1)->and(solde($payment->wallet))->toBe(0);
    }
})->with(['recharge', 'contribution']);

it('ne révèle ni les activités ni les échanges privés d’une autre personne dans Mon espace', function () {
    $owner = person('Titulaire', ['Couture']);
    $helper = person('Aidant', ['Comptabilité']);
    $conversation = conversationDe($owner, $helper);
    avantRecentrage(fn () => zumra($owner, 'Activité privée du titulaire'));
    $other = person('Autre compte', ['Menuiserie']);
    $page = Livewire::actingAs($other)->test(Espace::class)->call('show', 'echanges')->assertDontSeeText('Activité privée du titulaire')
        ->assertDontSee('href="'.route('conversations.show', $conversation).'"', false);
    expect($page->viewData('conversations')->total())->toBe(0);
    expect(Conversation::query()->count())->toBe(1);
});

it('affiche le menu global de bureau : Accueil, Personnes, Entreprises, recherche, notifications (icône seule) et Agir, Mon espace passant par le menu utilisateur', function () {
    $html = $this->actingAs(User::factory()->create())->get('/fil')->assertOk()->getContent();
    preg_match('/<nav aria-label="Navigation principale" class="hidden.*?<\/nav>/s', $html, $nav);
    $texte = trim(preg_replace('/\s+/', ' ', strip_tags($nav[0])));

    expect($texte)->toBe('Accueil Personnes Entreprises Opportunités')
        ->and($html)->not->toContain('min-[1700px]:flex')->toContain('href="'.route('entreprises.index').'"')->toContain('href="'.route('notifications').'"')->toContain('role="search"');
});

it('exécute les tests dans le parcours actif en production, sauf les modules gelés qui choisissent l’ancienne expérience', function () {
    expect(Experience::initial())->toBeTrue();

    legacyExperience();
    expect(Experience::initial())->toBeFalse();
});
