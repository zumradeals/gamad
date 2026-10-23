<?php

use App\Livewire\Fil;
use App\Livewire\GestureSheet;
use App\Livewire\PagePublication;
use App\Models\Publication;
use App\Models\User;
use App\Services\Gestures;
use App\Services\Publications;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->auteur = User::factory()->create(['name' => 'Yao Kouassi']);
    $this->ouverte = publication($this->auteur, data: ['body' => 'Publication ouverte à tous.']);
    $this->cachee = publication($this->auteur, data: ['body' => 'Publication réservée aux inscrits.', 'visibility' => 'members']);
});

/** Spectateurs qui n'ont pas le droit de voir une publication « inscrits seulement ». */
function sansDroit(): array
{
    return ['un visiteur sans compte' => null, 'une personne dont l’adresse n’est pas vérifiée' => User::factory()->unverified()->create()];
}

it('verrouille la publication de la page : changer publicationId dans la requête est refusé', function () {
    foreach (sansDroit() as $qui => $spectateur) {
        $composant = $spectateur ? Livewire::actingAs($spectateur) : Livewire::test(PagePublication::class, ['publication' => $this->ouverte]);
        $composant = $spectateur ? $composant->test(PagePublication::class, ['publication' => $this->ouverte]) : $composant;

        expect(fn () => $composant->set('publicationId', $this->cachee->id), $qui)->toThrow(CannotUpdateLockedPropertyException::class);
        $composant->assertDontSeeText('Publication réservée aux inscrits.')->assertSeeText('Publication ouverte à tous.');
    }

    // Même une personne connectée et vérifiée ne change pas de publication en route.
    $moi = User::factory()->create();
    expect(fn () => Livewire::actingAs($moi)->test(PagePublication::class, ['publication' => $this->ouverte])->set('publicationId', $this->cachee->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('verrouille la publication de la feuille : changer publicationId dans la requête est refusé', function () {
    $moi = User::factory()->create();
    $composant = Livewire::actingAs($moi)->test(GestureSheet::class)->dispatch('open-gesture', id: $this->ouverte->id);

    expect(fn () => $composant->set('publicationId', $this->cachee->id))->toThrow(CannotUpdateLockedPropertyException::class);
    $composant->assertSet('publicationId', $this->ouverte->id)->assertDontSeeText('Publication réservée');
});

it('revérifie le droit de voir à l’affichage de la page, même si l’identifiant a été changé malgré le verrou', function () {
    foreach (sansDroit() as $qui => $spectateur) {
        $spectateur ? $this->actingAs($spectateur) : app('auth')->forgetGuards();

        $page = new PagePublication;
        $page->publicationId = $this->cachee->id;

        try {
            $page->render();
            $this->fail("Rendu autorisé pour $qui");
        } catch (AuthorizationException $e) {
            expect($e->status())->toBe(404, $qui);
        }
    }

    // Une personne inscrite et vérifiée, elle, peut afficher la publication.
    $this->actingAs(User::factory()->create());
    $page = new PagePublication;
    $page->publicationId = $this->cachee->id;
    expect($page->render()->getData()['publication']->id)->toBe($this->cachee->id);
});

it('revérifie aussi le droit de voir à chaque action de la page', function () {
    $this->actingAs($this->auteur);
    geste(User::factory()->create(), $this->ouverte);

    app('auth')->forgetGuards();
    $page = new PagePublication;
    $page->publicationId = $this->cachee->id;

    expect(fn () => $page->accept((string) Str::uuid(), app(Gestures::class)))->toThrow(AuthorizationException::class)
        ->and(fn () => $page->resolve(app(Publications::class)))->toThrow(AuthorizationException::class);
});

it('revérifie le droit de voir à l’affichage de la feuille : une publication cachée n’est jamais montrée', function () {
    foreach (sansDroit() as $qui => $spectateur) {
        $spectateur ? $this->actingAs($spectateur) : app('auth')->forgetGuards();

        $feuille = new GestureSheet;
        $feuille->publicationId = $this->cachee->id;
        $vue = $feuille->render();

        expect($vue->getData()['publication'])->toBeNull($qui)->and($feuille->publicationId)->toBeNull($qui);
        expect($vue->render())->not->toContain('Yao')->not->toContain('Publication réservée');
    }

    $this->actingAs(User::factory()->create());
    $feuille = new GestureSheet;
    $feuille->publicationId = $this->cachee->id;
    expect($feuille->render()->getData()['publication']?->id)->toBe($this->cachee->id);
});

it('refuse d’ouvrir la feuille sur une publication cachée, sans rien montrer de son auteur', function () {
    Livewire::test(GestureSheet::class)->dispatch('open-gesture', id: $this->cachee->id)->assertForbidden()->assertSet('publicationId', null)->assertDontSeeText('Yao');

    $nonVerifie = User::factory()->unverified()->create();
    Livewire::actingAs($nonVerifie)->test(GestureSheet::class)->dispatch('open-gesture', id: $this->cachee->id)->assertForbidden()->assertSet('publicationId', null);
});

it('ne lie le nom de l’auteur à son profil que si ce profil est visible pour celui qui regarde (carte de publication)', function () {
    $discret = person('Auteur Discret', ['Couture'], visibility: 'members');
    $ouvert = person('Auteur Ouvert', ['Couture']);
    $p1 = publication($discret, data: ['body' => 'Texte du discret']);
    $p2 = publication($ouvert, data: ['body' => 'Texte de l’ouvert']);
    $lienDiscret = route('personnes.show', $discret->profile->public_id);
    $lienOuvert = route('personnes.show', $ouvert->profile->public_id);

    // Un visiteur sans compte : le nom de l'auteur ouvert est un lien, celui du discret est un simple texte.
    app('auth')->forgetGuards();
    $page1 = $this->get(route('publications.show', $p1))->assertOk()->assertSeeText('Auteur Discret')->getContent();
    $page2 = $this->get(route('publications.show', $p2))->assertOk()->assertSeeText('Auteur Ouvert')->getContent();
    expect($page1)->not->toContain($lienDiscret)->and($page2)->toContain($lienOuvert);

    // Une personne inscrite et vérifiée voit les deux profils : les deux noms sont des liens.
    $this->actingAs(User::factory()->create());
    expect($this->get(route('publications.show', $p1))->getContent())->toContain($lienDiscret);
});

it('ne lie pas le nom d’un auteur dont l’adresse e-mail n’est plus vérifiée', function () {
    $auteur = person('Auteur Public', ['Couture']);
    $p = publication($auteur, data: ['body' => 'Texte']);
    $auteur->forceFill(['email_verified_at' => null])->save();

    // Sa publication est alors invisible pour les autres : 404, et donc aucun lien nulle part.
    $this->actingAs(User::factory()->create())->get(route('publications.show', $p))->assertNotFound();
});

it('ne lie le nom de la personne qui a aidé que si son profil est visible pour l’auteur (écran Mon besoin)', function () {
    $aidantOuvert = person('Aidant Ouvert', ['Informatique']);
    $aidantDiscret = person('Aidant Discret', ['Informatique'], visibility: 'members');
    $aidantNonVerifie = person('Aidant Non Verifie', ['Informatique']);
    $besoin = publication($this->auteur, data: ['body' => 'Besoin avec trois réponses']);
    geste($aidantOuvert, $besoin);
    geste($aidantDiscret, $besoin);
    geste($aidantNonVerifie, $besoin);
    // L'adresse de cette personne cesse d'être vérifiée après sa réponse : son profil n'existe plus pour les autres.
    $aidantNonVerifie->forceFill(['email_verified_at' => null])->save();

    $html = $this->actingAs($this->auteur)->get(route('publications.show', $besoin))->assertOk()
        ->assertSeeText('Aidant Ouvert')->assertSeeText('Aidant Discret')->assertSeeText('Aidant Non Verifie')->getContent();

    expect($html)->toContain(route('personnes.show', $aidantOuvert->profile->public_id))
        // L'auteur est une personne vérifiée : il peut voir un profil « inscrits seulement ».
        ->toContain(route('personnes.show', $aidantDiscret->profile->public_id))
        // Un profil dont l'adresse n'est pas vérifiée n'existe pas pour lui : le nom reste un texte, sans lien.
        ->not->toContain(route('personnes.show', $aidantNonVerifie->profile->public_id));
});

it('applique la même règle dans le Fil : le nom n’est un lien que si le profil est visible', function () {
    $nonVerifieMaisPublicite = person('Auteur Ouvert', ['Couture']);
    $p = publication($nonVerifieMaisPublicite, data: ['body' => 'Dans le Fil']);

    $html = Livewire::actingAs(User::factory()->create())->test(Fil::class)->html();

    expect($html)->toContain(route('personnes.show', $nonVerifieMaisPublicite->profile->public_id));
});
