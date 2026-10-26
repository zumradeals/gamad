<?php

use App\Enums\PublicationType;
use App\Models\PublicationBookmark;
use App\Models\User;
use App\Services\Moderation;
use App\Services\People;
use App\Services\PublicationBookmarks;

beforeEach(function () {
    $this->auteur = person('Awa Traoré');
    $this->lecteur = person('Yao Kouassi');
    $this->publication = publication($this->auteur, PublicationType::Knowledge, ['body' => 'Astuce de couture à conserver.']);
});

function urlEnregistrer($publication): string
{
    return route('publications.bookmark', $publication);
}

it('enregistre une publication personnelle sans publicité des enregistrements et sans doublon', function () {
    $this->actingAs($this->lecteur)->post(urlEnregistrer($this->publication), ['saved' => '1'])->assertRedirect();
    $this->post(urlEnregistrer($this->publication), ['saved' => '1'])->assertRedirect();

    expect(PublicationBookmark::count())->toBe(1);
    $this->get(route('espace.enregistrements'))->assertOk()->assertSeeText('Astuce de couture à conserver.');
    $this->actingAs($this->auteur)->get(route('espace.enregistrements'))
        ->assertOk()->assertDontSeeText('Astuce de couture à conserver.');
});

it('permet de retirer un enregistrement et de le retrouver depuis Mon espace', function () {
    $this->actingAs($this->lecteur)->post(urlEnregistrer($this->publication), ['saved' => '1']);
    $this->get(route('espace'))->assertOk()->assertSeeText('Mes enregistrements');
    $this->post(urlEnregistrer($this->publication), ['saved' => '0'])->assertRedirect();
    expect(PublicationBookmark::count())->toBe(0);
    $this->get(route('espace.enregistrements'))->assertSeeText('Aucun enregistrement accessible');
});

it('n’affiche plus une publication masquée, même si elle reste enregistrée', function () {
    $this->actingAs($this->lecteur)->post(urlEnregistrer($this->publication), ['saved' => '1']);
    app(Moderation::class)->hide(User::factory()->moderator()->create(), $this->publication, 'Contenu à examiner');
    $this->get(route('espace.enregistrements'))->assertOk()->assertDontSeeText('Astuce de couture à conserver.');
    expect(PublicationBookmark::count())->toBe(1);
    $this->post(urlEnregistrer($this->publication), ['saved' => '0'])->assertRedirect();
    expect(PublicationBookmark::count())->toBe(0);
});

it('masque également les publications devenues réservées et celles dont les auteurs sont bloqués', function () {
    $this->actingAs($this->lecteur)->post(urlEnregistrer($this->publication), ['saved' => '1']);
    app(People::class)->block($this->lecteur, $this->auteur, true);
    $this->get(route('espace.enregistrements'))->assertOk()->assertDontSeeText('Astuce de couture à conserver.');
    $this->post(urlEnregistrer($this->publication), ['saved' => '0'])->assertRedirect();

    app(People::class)->block($this->lecteur, $this->auteur, false);
    $this->post(urlEnregistrer($this->publication), ['saved' => '1'])->assertRedirect();
    $this->publication->update(['visibility' => 'members']);
    auth()->logout();
    $this->get(route('publications.show', $this->publication))->assertNotFound();
});

it('refuse aux visiteurs et comptes non vérifiés d’enregistrer', function () {
    $this->post(urlEnregistrer($this->publication), ['saved' => '1'])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())
        ->post(urlEnregistrer($this->publication), ['saved' => '1'])->assertRedirect();
    expect(PublicationBookmark::count())->toBe(0);
});

it('ne permet pas de sauvegarder une publication interdite ou collective', function () {
    legacyExperience();
    $inactif = User::factory()->unverified()->create();
    $this->actingAs($this->lecteur);
    $post = publication($this->auteur, PublicationType::Knowledge, ['body' => 'Un savoir collectif.']);
    $org = zumra($this->auteur);
    $post->forceFill(['zumra_id' => $org->id])->save();
    $this->post(urlEnregistrer($post), ['saved' => '1'])->assertNotFound();

    $this->actingAs($inactif)->post(urlEnregistrer($this->publication), ['saved' => '1'])->assertRedirect();
    expect(PublicationBookmark::count())->toBe(0);
});

it('montre Partager avec un lien et le rappel de visibilité, sans publication automatique', function () {
    $this->actingAs($this->lecteur)->get(route('fil'))->assertOk()->assertSeeText('Partager')
        ->assertSee(urlEnregistrer($this->publication), false);
    $this->get(route('publications.show', $this->publication))->assertOk()
        ->assertSeeText('Partager')->assertSee('Lien de cette publication', false);

    $this->publication->update(['visibility' => 'members']);
    $this->get(route('publications.show', $this->publication))->assertOk()
        ->assertSeeText('Accès réservé aux inscrits autorisés');
});

it('conserve les enregistrements par cascade et ne laisse pas de contenu orphelin', function () {
    app(PublicationBookmarks::class)->set($this->lecteur, $this->publication, true);
    $this->publication->delete();
    expect(PublicationBookmark::count())->toBe(0);
});
