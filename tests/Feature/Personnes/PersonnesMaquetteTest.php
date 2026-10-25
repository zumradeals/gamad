<?php

use App\Livewire\CarrefourPersonnes;
use App\Models\City;
use App\Models\Domain;
use App\Models\PersonConnection;
use App\Models\User;
use App\Services\People;
use Livewire\Livewire;

function lesNoms($composant): array
{
    return $composant->viewData('people')->getCollection()->map(fn ($p) => $p->user->name)->all();
}

function page(?User $viewer = null)
{
    return $viewer ? Livewire::actingAs($viewer)->test(CarrefourPersonnes::class) : Livewire::test(CarrefourPersonnes::class);
}

beforeEach(function () {
    $this->moi = person('Moi Même', ['Menuiserie'], ['Comptabilité'], 'Bouaké', domains: ['Économie et Finances']);
    $this->awa = person('Awa Traoré', ['Comptabilité'], ['Anglais'], 'Bouaké', domains: ['Économie et Finances']);
    $this->yao = person('Yao Kouassi', ['Jardinage'], ['Menuiserie'], 'Korhogo', domains: ['Agriculture, Pêche et Élevage']);
    $this->zoe = person('Zoé Konan', ['Couture'], [], 'Korhogo');
    $this->vide = person('Sans Capacité');
});

it('ouvre par défaut l’onglet « Tous » : toute personne ayant une capacité, dans un sens ou l’autre', function () {
    $noms = lesNoms(page());

    expect($noms)->toBe(['Awa Traoré', 'Moi Même', 'Yao Kouassi', 'Zoé Konan']);
    page()->assertSet('mode', 'all')->assertSeeText('4 personnes à découvrir');
});

it('sépare « Peut apporter » et « Veut apprendre »', function () {
    expect(lesNoms(page()->call('setMode', 'offered')))->toBe(['Awa Traoré', 'Moi Même', 'Yao Kouassi', 'Zoé Konan'])
        ->and(lesNoms(page()->call('setMode', 'wanted')))->toBe(['Awa Traoré', 'Moi Même', 'Yao Kouassi']);
});

it('cherche dans les deux sens sous l’onglet Tous', function () {
    expect(lesNoms(page()->set('query', 'anglais')))->toBe(['Awa Traoré'])
        ->and(lesNoms(page()->set('query', 'couture')))->toBe(['Zoé Konan']);
});

it('trie explicitement : A→Z, Z→A, arrivées récentes ; une valeur inconnue retombe sur A→Z', function () {
    expect(lesNoms(page()->set('sort', 'nom-desc')))->toBe(['Zoé Konan', 'Yao Kouassi', 'Moi Même', 'Awa Traoré'])
        ->and(lesNoms(page()->set('sort', 'recents')))->toHaveCount(4)
        ->and(lesNoms(page()->set('sort', 'pertinence')))->toBe(['Awa Traoré', 'Moi Même', 'Yao Kouassi', 'Zoé Konan']);
    page()->assertSeeText('Nom (A → Z)')->assertDontSeeText('Pertinence');
});

it('affiche des compteurs réels par domaine et par localité', function () {
    $economie = Domain::query()->where('name', 'Économie et Finances')->value('id');
    $agriculture = Domain::query()->where('name', 'Agriculture, Pêche et Élevage')->value('id');
    $bouake = City::query()->where('name', 'Bouaké')->value('id');
    $korhogo = City::query()->where('name', 'Korhogo')->value('id');

    $p = page();
    expect($p->viewData('domainCounts'))->toMatchArray([$economie => 2, $agriculture => 1])
        ->and($p->viewData('cityCounts'))->toMatchArray([$bouake => 2, $korhogo => 2]);

    // Un compteur tient compte des autres filtres, pas du sien.
    $p->set('city', $korhogo);
    expect($p->viewData('domainCounts'))->toBe([$agriculture => 1]);
});

it('propose les onglets « Par domaine » et « Par localité » comme regroupements cliquables', function () {
    $agriculture = Domain::query()->where('name', 'Agriculture, Pêche et Élevage')->value('id');

    $p = page()->call('setMode', 'domains')->assertSeeText('Choisissez un domaine')->assertSeeText('Agriculture, Pêche et Élevage · 1');
    expect(lesNoms($p->set('domain', $agriculture)))->toBe(['Yao Kouassi']);

    page()->call('setMode', 'cities')->assertSeeText('Choisissez une localité')->assertSeeText('Korhogo · 2');
});

it('ignore un onglet, une liste ou un tri inconnus', function () {
    expect(lesNoms(page($this->moi)->call('setMode', 'x')->call('setList', 'x')))->toHaveCount(4);
    page()->call('setMode', 'x')->assertSet('mode', 'all');
});

it('réserve suivies, contacts, suggestions et autour de moi aux personnes connectées', function () {
    page()->assertDontSeeText('Personnes suivies')->assertDontSeeText('Autour de moi')->assertSeeText('Toutes les personnes');
    // Un visiteur qui force la liste reste sur toutes les personnes.
    expect(lesNoms(page()->set('list', 'suivies')))->toHaveCount(4);

    page($this->moi)->assertSeeText('Personnes suivies')->assertSeeText('Mes contacts')->assertSeeText('Suggestions')->assertSeeText('Autour de moi');
});

it('liste les personnes que je suis, et seulement elles', function () {
    app(People::class)->follow($this->moi, $this->yao, true);

    $p = page($this->moi)->call('setList', 'suivies');
    expect(lesNoms($p))->toBe(['Yao Kouassi']);
    $p->assertSeeText('Suivie');

    expect(lesNoms(page($this->awa)->call('setList', 'suivies')))->toBe([]);
    page($this->awa)->call('setList', 'suivies')->assertSeeText('Vous ne suivez encore personne.');
});

it('suit et cesse de suivre depuis le menu d’une carte, sans ouvrir de contact', function () {
    $p = page($this->moi)->call('follow', $this->yao->profile->id, true);
    expect(DB::table('person_follows')->where('user_id', $this->moi->id)->where('followed_id', $this->yao->id)->exists())->toBeTrue();
    $p->assertSeeText('Ne plus suivre');

    $p->call('follow', $this->yao->profile->id, false);
    expect(DB::table('person_follows')->count())->toBe(0);
    expect(DB::table('person_connections')->count())->toBe(0);
});

it('refuse de suivre sans compte, soi-même ou une personne qui m’a bloqué', function () {
    page()->call('follow', $this->yao->profile->id, true)->assertForbidden();
    page($this->moi)->call('follow', $this->moi->profile->id, true)->assertForbidden();

    app(People::class)->block($this->yao, $this->moi, true);
    page($this->moi)->call('follow', $this->yao->profile->id, true)->assertNotFound();
    expect(lesNoms(page($this->moi)))->not->toContain('Yao Kouassi');
});

it('liste mes contacts : relations réciproques acceptées seulement', function () {
    $people = app(People::class);
    $people->request($this->moi, $this->awa, 'Bonjour');
    expect(lesNoms(page($this->moi)->call('setList', 'contacts')))->toBe([]);

    $people->decide($this->awa, $this->moi, 'accept', 1);
    $p = page($this->moi)->call('setList', 'contacts');
    expect(lesNoms($p))->toBe(['Awa Traoré']);
    $p->assertSeeText('Contact');
});

it('relie Contacter à une fonction réelle : la conversation du contact, sinon le profil et son formulaire', function () {
    $people = app(People::class);

    page($this->moi)->assertSee(route('personnes.show', $this->yao->profile->public_id).'#contact', false);

    $people->request($this->moi, $this->awa, 'Bonjour');
    $people->decide($this->awa, $this->moi, 'accept', 1);
    $conversation = PersonConnection::query()->forPerson($this->moi)->value('conversation_id');
    expect($conversation)->not->toBeNull();
    page($this->moi)->assertSee(route('conversations.show', $conversation), false);
});

it('propose des suggestions explicables, à partir des capacités complémentaires', function () {
    $suggestions = page($this->moi)->viewData('suggestions');

    expect($suggestions->pluck('user.name')->all())->toBe(['Awa Traoré', 'Yao Kouassi'])
        ->and($suggestions->firstWhere('user.name', 'Awa Traoré')->reason)->toBe('Peut vous apporter : Comptabilité')
        ->and($suggestions->firstWhere('user.name', 'Yao Kouassi')->reason)->toBe('Veut apprendre : Menuiserie, que vous pouvez apporter');

    expect(lesNoms(page($this->moi)->call('setList', 'suggestions')))->toBe(['Awa Traoré', 'Yao Kouassi']);
    page($this->moi)->assertSeeText('Suggestions pour vous')->assertSeeText('Peut vous apporter : Comptabilité');
    // Sans capacité déclarée, aucune suggestion ni bloc.
    page($this->vide)->assertDontSeeText('Suggestions pour vous');
    page($this->vide)->call('setList', 'suggestions')->assertSeeText('Aucune suggestion pour le moment.');
});

it('montre « Autour de moi » par la localité déclarée, jamais par une position', function () {
    expect(lesNoms(page($this->moi)->call('setList', 'autour')))->toBe(['Awa Traoré']);
    page($this->moi)->assertSeeText('Personnes près de vous')->assertSeeText('Même localité déclarée');

    // Sans localité, le bloc disparaît et la liste invite à la renseigner.
    page($this->vide)->assertDontSeeText('Personnes près de vous');
    page($this->vide)->call('setList', 'autour')->assertSeeText('Indiquez votre localité');
});

it('n’affiche ni note, ni classement, ni compteur de popularité, ni téléphone ou e-mail', function () {
    $html = page($this->moi)->html();

    expect($html)->not->toContain('classement')->not->toContain('populaire')->not->toContain('abonnés')
        ->not->toContain($this->awa->email)->not->toContain('Vérifié');
});

it('masque le menu d’actions pour un visiteur et pour sa propre carte', function () {
    page()->assertDontSeeText('Suivre cette personne');
    $p = page($this->moi);
    expect($p->html())->toContain('Suivre cette personne')->toContain('Vous');
    expect(substr_count($p->html(), 'Autres actions pour'))->toBe(3);
});

it('affiche la présentation courte réelle de la personne, sans texte inventé', function () {
    $this->awa->profile->update(['headline' => 'Comptable et formatrice']);

    page()->assertSeeText('Comptable et formatrice');
    page()->assertDontSeeText('Disponible')->assertDontSeeText('En ligne');
});

it('élargit la page Personnes à 1480 px ; l’en-tête et Mon espace gardent 1240 px', function () {
    $personnes = $this->actingAs($this->moi)->get(route('personnes.index'))->assertOk()->getContent();
    $autre = $this->actingAs($this->moi)->get(route('espace'))->assertOk()->getContent();

    expect($personnes)->toContain('max-w-[1480px]')->toContain('max-w-[1240px]')
        ->and($autre)->not->toContain('max-w-[1480px]');
});
