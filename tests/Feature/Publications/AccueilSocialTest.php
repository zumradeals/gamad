<?php

use App\Enums\PublicationType;
use App\Livewire\Fil;
use App\Services\People;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('découvre des publications publiques de personnes non suivies, sans révéler celles de son réseau ni les siennes', function () {
    $moi = person('Lecteur social');
    $suivi = person('Auteur déjà suivi');
    $nouveau = person('Nouveau talent');
    $autre = person('Autre nouveau talent');

    $monPost = publication($moi, PublicationType::Share, ['body' => 'Ma propre expérience']);
    $deja = publication($suivi, PublicationType::Share, ['body' => 'Un savoir déjà suivi']);
    $decouverte = publication($nouveau, PublicationType::Share, ['body' => 'Un partage inédit']);
    $public = publication($autre, PublicationType::Knowledge, ['body' => 'Un savoir ouvert']);
    $reserve = publication($nouveau, PublicationType::Knowledge, ['body' => 'Contenu réservé', 'visibility' => 'members']);

    app(People::class)->follow($moi, $suivi, true);
    $feed = Livewire::actingAs($moi)->test(Fil::class)->call('setFilter', 'decouvrir');
    $feed->assertSeeText('Un partage inédit')->assertSeeText('Un savoir ouvert')
        ->assertDontSeeText('Ma propre expérience')->assertDontSeeText('Un savoir déjà suivi')
        ->assertDontSeeText('Contenu réservé')
        ->assertSeeText('Publications publiques de nouvelles personnes');

    expect($decouverte->isPublic())->toBeTrue();
});

it('respecte l’ordre chronologique et non la popularité dans Découvrir', function () {
    $lecteur = person('Observatrice');
    $ancienne = publication(person('Auteur ancien'), PublicationType::Knowledge, ['body' => 'Ancienne expérience']);
    $recente = publication(person('Auteur récent'), PublicationType::Knowledge, ['body' => 'Expérience récente']);
    $ancienne->forceFill(['created_at' => now()->subDays(2)])->save();
    $recente->forceFill(['created_at' => now()->subHour()])->save();

    $html = Livewire::actingAs($lecteur)->test(Fil::class)->call('setFilter', 'decouvrir')->html();
    expect(strpos($html, 'Expérience récente'))->toBeLessThan(strpos($html, 'Ancienne expérience'))
        ->and($html)->not->toContain('J’aime')->not->toContain('Like');
});

it('regroupe expériences et réalisations personnelles dans Partages et laisse les besoins dans leur filtre', function () {
    $auteur = person('Artisane');
    publication($auteur, PublicationType::Share, ['body' => 'Histoire de mon parcours']);
    publication($auteur, PublicationType::PersonalWork, ['body' => 'Mon ouvrage terminé']);
    publication($auteur, PublicationType::Need, ['body' => 'Besoin de soutien']);

    Livewire::actingAs(person('Lectrice'))->test(Fil::class)->call('setFilter', 'partages')
        ->assertSeeText('Histoire de mon parcours')->assertSeeText('Mon ouvrage terminé')
        ->assertDontSeeText('Besoin de soutien')->assertSeeText('Partages et réalisations récents');
});

it('montre les publications accessibles des personnes suivies, y compris celles réservées aux inscrits', function () {
    $viewer = person('Suiveuse');
    $other = person('Suivi');
    $unfollowed = person('Non suivi');
    app(People::class)->follow($viewer, $other, true);
    publication($other, PublicationType::Share, ['body' => 'Journal public']);
    publication($other, PublicationType::Knowledge, ['body' => 'Journal membres', 'visibility' => 'members']);
    publication($unfollowed, PublicationType::Share, ['body' => 'Auteur extérieur']);

    Livewire::actingAs($viewer)->test(Fil::class)->call('setFilter', 'suivis')
        ->assertSeeText('Journal public')->assertSeeText('Journal membres')
        ->assertDontSeeText('Auteur extérieur')->assertSeeText('Publications récentes des personnes suivies');
});

it('autorise le suivi depuis une publication visible mais pas depuis sa propre publication', function () {
    $viewer = person('Visiteuse');
    $auteur = person('Autrice');
    $post = publication($auteur, PublicationType::Share, ['body' => 'Mon expérience professionnelle']);

    $feed = Livewire::actingAs($viewer)->test(Fil::class);
    $feed->assertSeeText('+ Suivre')->call('followAuthor', $post->id)->assertHasNoErrors()
        ->assertSeeText('Personne suivie');

    expect(DB::table('person_follows')->where('user_id', $viewer->id)->where('followed_id', $auteur->id)->count())->toBe(1);
    $feed->call('setFilter', 'decouvrir')->assertDontSeeText('Mon expérience professionnelle');
    $feed->call('setFilter', 'suivis')->assertSeeText('Mon expérience professionnelle');
    $feed->call('followAuthor', $post->id)->assertHasNoErrors();
    expect(DB::table('person_follows')->where('user_id', $viewer->id)->where('followed_id', $auteur->id)->count())->toBe(1);

    $own = publication($viewer, PublicationType::Share, ['body' => 'Ma propre réalisation']);
    Livewire::actingAs($viewer)->test(Fil::class)->call('followAuthor', $own->id)->assertForbidden();
});

it('ne découvre aucun contenu de personne bloquée et refuse le suivi malgré un identifiant manipulé', function () {
    $viewer = person('Lecteur privé');
    $auteur = person('Personne bloquée');
    $post = publication($auteur, PublicationType::Share, ['body' => 'Ne pas faire apparaître']);

    app(People::class)->block($viewer, $auteur, true);
    Livewire::actingAs($viewer)->test(Fil::class)->call('setFilter', 'decouvrir')
        ->assertDontSeeText('Ne pas faire apparaître');
    // La couche Eloquent refuse la publication masquée avant même le service de suivi.
    expect(fn () => Livewire::actingAs($viewer)->test(Fil::class)->call('followAuthor', $post->id))
        ->toThrow(ModelNotFoundException::class);
    expect(DB::table('person_follows')->where('user_id', $viewer->id)->where('followed_id', $auteur->id)->exists())->toBeFalse();
});

it('garde le signalement et les interactions sociales sans classement public', function () {
    $auteur = person('Auteur social');
    $viewer = person('Lecteur social');
    publication($auteur, PublicationType::Share, ['body' => 'Mon apprentissage réel']);

    Livewire::actingAs($viewer)->test(Fil::class)
        ->assertSeeText('+ Suivre')->assertSeeText('Signaler')->assertSeeText('Commenter')
        ->assertSeeText('Enregistrer')->assertSeeText('Partager')->assertDontSeeText('J’aime');
});

it('réinitialise la pagination au changement vers Découvrir et sur les raccourcis existants', function () {
    $auteur = person('Auteur de nombreux posts');
    foreach (range(1, 13) as $i) {
        publication($auteur, PublicationType::Share, ['body' => sprintf('Expérience %02d', $i)]);
    }

    Livewire::actingAs(person('Lecteur régulier'))->test(Fil::class)
        ->call('more')->assertSet('limit', 20)
        ->call('setFilter', 'decouvrir')->assertSet('limit', 10)->assertSet('filter', 'decouvrir')
        ->call('setFilter', 'savoirs')->assertSet('filter', 'savoirs')
        ->call('setFilter', 'filtre_inconnu')->assertSet('filter', 'tout');
});
