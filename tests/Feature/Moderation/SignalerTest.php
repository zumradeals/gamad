<?php

use App\Enums\PublicationType;
use App\Enums\ReportCategory;
use App\Livewire\Fil;
use App\Livewire\SignalerPublication;
use App\Models\Publication;
use App\Models\Report;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Reports;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->auteur = User::factory()->create(['name' => 'Awa Traoré']);
    $this->personne = User::factory()->create(['name' => 'Yao Kouassi']);
    $this->atelier = publication($this->auteur, PublicationType::Knowledge, ['body' => 'Atelier ouvert à tous : tenir les comptes d’un petit commerce.']);
});

function signaler(User $qui, Publication $publication, ?string $categorie = 'spam', ?string $precision = null): Report
{
    return app(Reports::class)->submit($qui, $publication, $categorie, $precision);
}

// ─── Envoyer un signalement ──────────────────────────────────────────────────

it('enregistre un signalement avec la raison choisie et la précision', function () {
    Livewire::actingAs($this->personne)->test(SignalerPublication::class, ['publication' => $this->atelier])
        ->set('category', 'scam')->set('details', 'On me demande de payer à l’avance.')->call('send')
        ->assertHasNoErrors()->assertRedirect(route('publications.show', $this->atelier));

    $r = Report::query()->sole();
    expect($r->category)->toBe(ReportCategory::Scam)->and($r->details)->toBe('On me demande de payer à l’avance.')
        ->and($r->reporter_id)->toBe($this->personne->id)->and($r->publication_id)->toBe($this->atelier->id)->and($r->resolution)->toBeNull();
});

it('affiche l’écran 16 : les six raisons, la précision, le rappel de confidentialité', function () {
    $this->actingAs($this->personne)->get(route('publications.signaler', $this->atelier))->assertOk()
        ->assertSeeText('Signaler cette publication')
        ->assertSeeText('Contenu offensant ou haineux')->assertSeeText('Arnaque ou demande d’argent suspecte')->assertSeeText('Fausse information')
        ->assertSeeText('Publicité ou spam')->assertSeeText('Atteinte à la vie privée')->assertSeeText('Autre')
        ->assertSee('Précisez si vous le souhaitez…', false)->assertSeeText('/ 280')
        ->assertSeeText('L’auteur ne saura pas qui a signalé.')->assertSeeText('Avant toute sanction, l’auteur peut s’expliquer.')
        ->assertSeeText('Envoyer le signalement')->assertSeeText('Atelier ouvert à tous');
});

it('exige une raison de la liste', function () {
    foreach ([null, '', 'inconnue', 'SPAM'] as $mauvaise) {
        expect(fn () => signaler($this->personne, $this->atelier, $mauvaise))->toThrow(ValidationException::class);
    }

    Livewire::actingAs($this->personne)->test(SignalerPublication::class, ['publication' => $this->atelier])->call('send')->assertHasErrors(['category']);
    expect(Report::count())->toBe(0);
});

it('accepte 280 caractères de précision et refuse 281', function () {
    signaler($this->personne, $this->atelier, 'other', str_repeat('é', 280));
    expect(fn () => signaler(User::factory()->create(), $this->atelier, 'other', str_repeat('é', 281)))->toThrow(ValidationException::class);

    expect(Report::count())->toBe(1);
});

it('la précision est facultative', function () {
    expect(signaler($this->personne, $this->atelier, 'privacy', '   ')->details)->toBeNull();
});

it('n’accepte qu’un signalement par personne et par publication, aussi au niveau de la base', function () {
    signaler($this->personne, $this->atelier, 'spam');

    expect(fn () => signaler($this->personne, $this->atelier, 'scam'))->toThrow(ValidationException::class, 'déjà signalé');

    // La base refuse aussi le doublon, même si deux envois partent en même temps.
    expect(fn () => DB::transaction(fn () => Report::query()->insert(['id' => (string) Str::uuid(), 'publication_id' => $this->atelier->id, 'reporter_id' => $this->personne->id, 'category' => 'spam', 'created_at' => now(), 'updated_at' => now()])))
        ->toThrow(UniqueConstraintViolationException::class);

    // Une autre personne peut signaler la même publication.
    signaler(User::factory()->create(), $this->atelier, 'spam');
    expect(Report::count())->toBe(2);
});

it('montre « déjà signalé » à qui rouvre l’écran', function () {
    signaler($this->personne, $this->atelier, 'spam');

    $this->actingAs($this->personne)->get(route('publications.signaler', $this->atelier))->assertOk()->assertSeeText('Vous avez déjà signalé cette publication.')->assertDontSeeText('Envoyer le signalement');
});

it('limite le débit des signalements : par heure et par jour (D-086)', function () {
    config(['gamad.limits.reports_per_hour' => 2, 'gamad.limits.reports_per_day' => 3]);
    $autres = collect(range(1, 4))->map(fn () => publication($this->auteur, data: ['body' => 'Une autre']));

    signaler($this->personne, $autres[0]);
    signaler($this->personne, $autres[1]);

    expect(fn () => signaler($this->personne, $autres[2]))->toThrow(ValidationException::class, 'trop vite');
    expect(Report::count())->toBe(2);

    // La limite est par personne.
    signaler(User::factory()->create(), $autres[2]);
    expect(Report::count())->toBe(3);
});

// ─── Qui peut signaler ───────────────────────────────────────────────────────

it('ne laisse pas signaler sa propre publication', function () {
    expect(fn () => signaler($this->auteur, $this->atelier))->toThrow(AuthorizationException::class);

    $this->actingAs($this->auteur)->get(route('publications.signaler', $this->atelier))->assertForbidden();
    expect(Report::count())->toBe(0);
});

it('demande de se connecter à un visiteur, et de vérifier son adresse à une personne non vérifiée', function () {
    $this->get(route('publications.signaler', $this->atelier))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())->get(route('publications.signaler', $this->atelier))->assertRedirect();
    expect(fn () => signaler(User::factory()->unverified()->create(), $this->atelier))->toThrow(AuthorizationException::class);
});

it('répond « introuvable » (404) pour une publication qu’on ne peut pas voir, et ne la révèle pas', function () {
    $nonVerifie = User::factory()->unverified()->create();
    $cachee = new Publication(['type' => PublicationType::Knowledge, 'body' => 'Texte secret', 'visibility' => 'public']);
    $cachee->author()->associate($nonVerifie);
    $cachee->save();

    $this->actingAs($this->personne)->get(route('publications.signaler', $cachee))->assertNotFound();
    expect(fn () => signaler($this->personne, $cachee))->toThrow(AuthorizationException::class);
});

it('verrouille la publication de l’écran et revérifie le droit à l’affichage', function () {
    $autre = publication($this->auteur, data: ['body' => 'Une autre']);

    $composant = Livewire::actingAs($this->personne)->test(SignalerPublication::class, ['publication' => $this->atelier]);
    expect(fn () => $composant->set('publicationId', $autre->id))->toThrow(CannotUpdateLockedPropertyException::class);

    // Même avec un identifiant changé malgré le verrou, la publication de l'auteur lui-même n'est jamais rendue.
    $this->actingAs($this->auteur);
    $page = new SignalerPublication;
    $page->publicationId = $autre->id;
    expect(fn () => $page->render())->toThrow(AuthorizationException::class);
});

it('ne révèle jamais qui a signalé : ni à l’auteur, ni dans ses écrans, ni dans ses notifications', function () {
    signaler($this->personne, $this->atelier, 'offensive', 'Insultes dans les commentaires');

    $pages = [
        $this->actingAs($this->auteur)->get(route('publications.show', $this->atelier))->getContent(),
        $this->get(route('espace'))->getContent(),
        $this->get(route('notifications'))->getContent(),
    ];

    foreach ($pages as $html) {
        expect($html)->not->toContain('Yao Kouassi')->not->toContain($this->personne->email)->not->toContain('Insultes dans les commentaires');
    }

    expect(UserNotification::query()->where('user_id', $this->auteur->id)->count())->toBe(0);
});

it('montre « Signaler » aux personnes connectées sur la publication d’un autre, pas sur la leur ni aux visiteurs', function () {
    $this->actingAs($this->personne)->get(route('publications.show', $this->atelier))->assertSeeText('Signaler')->assertSee(route('publications.signaler', $this->atelier), false);
    Livewire::actingAs($this->personne)->test(Fil::class)->assertSee(route('publications.signaler', $this->atelier), false);

    $this->actingAs($this->auteur)->get(route('publications.show', $this->atelier))->assertDontSee(route('publications.signaler', $this->atelier), false);

    app('auth')->forgetGuards();
    $this->get(route('publications.show', $this->atelier))->assertOk()->assertDontSee(route('publications.signaler', $this->atelier), false);
});
