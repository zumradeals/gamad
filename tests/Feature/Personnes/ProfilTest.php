<?php

use App\Livewire\ModifierProfil;
use App\Models\AdminAction;
use App\Models\City;
use App\Models\Country;
use App\Models\Domain;
use App\Models\Skill;
use App\Models\User;
use App\Services\ProfileEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

function editeur(?User $user = null)
{
    return Livewire::actingAs($user ?? User::factory()->create())->test(ModifierProfil::class);
}

function chercherVille(string $nom): City
{
    return City::query()->where('name', $nom)->firstOrFail();
}

it('est réservé aux personnes connectées et vérifiées', function () {
    $this->get('/espace/profil')->assertRedirect('/login');
    $this->actingAs(User::factory()->unverified()->create())->get('/espace/profil')->assertRedirect();
    $this->actingAs(User::factory()->create())->get('/espace/profil')->assertOk()->assertSeeText('Modifier mon profil');
});

it('crée un profil dès l’inscription, avec un identifiant public aléatoire', function () {
    $user = User::factory()->create(['name' => 'Awa Traoré', 'email' => 'awa@example.test']);
    $profile = $user->profile;

    expect($profile)->not->toBeNull()
        ->and($profile->public_id)->toMatch('/^[a-z0-9]{12}$/')
        ->and($profile->visibility)->toBe('public');
});

it('propose chaque profil sous un identifiant unique', function () {
    $ids = User::factory()->count(20)->create()->map(fn (User $user) => $user->profile->public_id);

    expect($ids->unique())->toHaveCount(20);
});

it('enregistre présentation, pays, ville, visibilité, capacités et domaines, puis confirme', function () {
    $user = User::factory()->create();
    $ville = chercherVille('Yamoussoukro');
    $domaines = Domain::query()->whereIn('name', ['Économie et Finances', 'Affaires commerciales'])->pluck('id')->all();

    editeur($user)
        ->set('bio', "Comptable de formation.\nJ’aide les petits commerces.")
        ->set('country_id', $ville->country_id)
        ->set('city_id', $ville->id)
        ->set('visibility', 'members')
        ->set('offered', ['Comptabilité', 'Tableur Excel'])
        ->set('wanted', ['Vente sur WhatsApp'])
        ->set('domain_ids', $domaines)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', 'Profil enregistré.')
        ->assertSeeText('Profil enregistré.');

    $profile = $user->fresh()->profile;

    expect($profile->bio)->toBe("Comptable de formation.\nJ’aide les petits commerces.")
        ->and($profile->city_id)->toBe($ville->id)
        ->and($profile->visibility)->toBe('members')
        ->and($profile->offeredSkills->pluck('name')->all())->toBe(['Comptabilité', 'Tableur Excel'])
        ->and($profile->wantedSkills->pluck('name')->all())->toBe(['Vente sur WhatsApp'])
        ->and($profile->domains->pluck('name')->all())->toBe(['Économie et Finances', 'Affaires commerciales']);
});

it('recharge ce qui est enregistré quand on rouvre l’écran', function () {
    $user = person('Awa Traoré', ['Comptabilité'], ['Anglais'], 'Yamoussoukro', bio: 'Bonjour');

    editeur($user)
        ->assertSet('bio', 'Bonjour')
        ->assertSet('offered', ['Comptabilité'])
        ->assertSet('wanted', ['Anglais'])
        ->assertSet('city_id', chercherVille('Yamoussoukro')->id);
});

it('ne crée « Comptabilité », « comptabilite » et « COMPTABILITÉ » qu’une seule fois, même venant de trois personnes', function () {
    person('Une', ['Comptabilité']);
    person('Deux', ['comptabilite']);
    person('Trois', ['COMPTABILITÉ'], ['  comptabilité  ']);

    expect(Skill::query()->where('normalized', 'comptabilite')->count())->toBe(1)
        ->and(DB::table('profile_skill')->count())->toBe(4)
        ->and(Skill::query()->where('normalized', 'comptabilite')->value('name'))->toBe('Comptabilité');
});

it('ne garde qu’une fois une capacité saisie deux fois dans la même liste', function () {
    $user = person('Awa', ['Couture', 'couture', ' COUTURE ']);

    expect($user->profile->offeredSkills->pluck('name')->all())->toBe(['Couture']);
});

it('accepte exactement dix capacités par liste et refuse la onzième', function () {
    $dix = collect(range(1, 10))->map(fn ($i) => "Capacité $i")->all();
    $user = User::factory()->create();

    editeur($user)->set('offered', $dix)->set('wanted', $dix)->call('save')->assertHasNoErrors();
    expect($user->profile->offeredSkills()->count())->toBe(10)->and($user->profile->wantedSkills()->count())->toBe(10);

    editeur($user)->set('offered', [...$dix, 'Capacité 11'])->call('save')->assertHasErrors(['offered']);
    editeur($user)->set('wanted', [...$dix, 'Capacité 11'])->call('save')->assertHasErrors(['wanted']);
    expect($user->profile->offeredSkills()->count())->toBe(10);
});

it('refuse d’ajouter une onzième capacité depuis l’écran et le dit', function () {
    $dix = collect(range(1, 10))->map(fn ($i) => "Capacité $i")->all();

    editeur()->set('offered', $dix)->set('offeredInput', 'Une de trop')->call('addSkill', 'offered')
        ->assertHasErrors(['offeredInput'])
        ->assertCount('offered', 10)
        ->assertSeeText('Dix capacités au plus');
});

it('compte les dix capacités séparément pour « apporte » et « apprend »', function () {
    $dix = collect(range(1, 10))->map(fn ($i) => "Capacité $i")->all();
    $user = person('Awa', $dix, $dix);

    expect($user->profile->offeredSkills()->count())->toBe(10)->and($user->profile->wantedSkills()->count())->toBe(10);
});

it('refuse une capacité de moins de 2 ou de plus de 60 caractères', function () {
    $user = User::factory()->create();

    editeur($user)->set('offered', ['a'])->call('save')->assertHasErrors(['offered']);
    editeur($user)->set('offered', [str_repeat('é', 61)])->call('save')->assertHasErrors(['offered']);
    editeur($user)->set('offered', [str_repeat('é', 60)])->call('save')->assertHasNoErrors();
    editeur($user)->set('wanted', ['ab'])->call('save')->assertHasNoErrors();
    editeur($user)->set('wanted', ['  a  '])->call('save')->assertHasErrors(['wanted']);
    editeur($user)->set('wanted', ['!!'])->call('save')->assertHasErrors(['wanted']);
});

it('valide aussi à l’ajout depuis l’écran', function () {
    editeur()->set('offeredInput', 'a')->call('addSkill', 'offered')->assertHasErrors(['offeredInput'])->assertCount('offered', 0);
    editeur()->set('offeredInput', str_repeat('x', 61))->call('addSkill', 'offered')->assertHasErrors(['offeredInput']);
    editeur()->set('offeredInput', 'Couture')->call('addSkill', 'offered')->assertHasNoErrors()->assertSet('offered', ['Couture'])->assertSet('offeredInput', '');
});

it('reprend l’écriture d’une capacité déjà existante quand on en ajoute une variante', function () {
    person('Awa', ['Comptabilité']);

    editeur()->set('offeredInput', 'COMPTABILITE')->call('addSkill', 'offered')->assertSet('offered', ['Comptabilité']);
});

it('ajoute puis retire une capacité', function () {
    editeur()
        ->set('offeredInput', 'Couture')->call('addSkill', 'offered')
        ->set('offeredInput', 'Cuisine')->call('addSkill', 'offered')
        ->assertSet('offered', ['Couture', 'Cuisine'])
        ->call('removeSkill', 'offered', 0)
        ->assertSet('offered', ['Cuisine']);
});

it('n’enregistre rien tant qu’on n’a pas touché « Enregistrer », et ne crée pas de capacité orpheline', function () {
    $user = User::factory()->create();

    editeur($user)->set('offeredInput', 'Capacité abandonnée')->call('addSkill', 'offered');

    expect(Skill::query()->where('normalized', 'capacite abandonnee')->exists())->toBeFalse()
        ->and($user->profile->offeredSkills()->count())->toBe(0);
});

it('propose d’abord les capacités déjà utilisées, avec « déjà utilisée »', function () {
    person('Awa', ['Couture', 'Couture de vêtements traditionnels', 'Cuisine']);

    editeur()
        ->set('offeredInput', 'coutu')
        ->assertSeeText('Couture')
        ->assertSeeText('Couture de vêtements traditionnels')
        ->assertSeeText('déjà utilisée')
        ->assertSeeText('Ajouter « coutu »')
        ->assertDontSeeText('Cuisine');
});

it('ne propose pas « Ajouter » quand la capacité existe déjà exactement', function () {
    person('Awa', ['Couture']);

    editeur()->set('offeredInput', 'couture')->assertSeeText('déjà utilisée')->assertDontSeeText('Ajouter « couture »');
});

it('ne propose plus une capacité que plus personne n’utilise', function () {
    $awa = person('Awa', ['Couture']);
    editeur($awa)->set('offered', [])->call('save');

    editeur()->set('offeredInput', 'coutu')->assertDontSeeText('déjà utilisée');
});

it('ne propose pas une capacité déjà dans la liste', function () {
    person('Awa', ['Couture']);

    editeur()->set('offered', ['Couture'])->set('offeredInput', 'couture')->assertSeeText('déjà dans votre liste')->assertDontSeeText('Ajouter « couture »');
});

it('accepte au plus 3 domaines', function () {
    $ids = Domain::query()->ordered()->limit(4)->pluck('id')->all();
    $user = User::factory()->create();

    editeur($user)->set('domain_ids', array_slice($ids, 0, 3))->call('save')->assertHasNoErrors();
    expect($user->profile->domains()->count())->toBe(3);

    editeur($user)->set('domain_ids', $ids)->call('save')->assertHasErrors(['domain_ids']);
    expect($user->profile->domains()->count())->toBe(3);
});

it('refuse un quatrième domaine depuis l’écran', function () {
    $ids = Domain::query()->ordered()->limit(4)->pluck('id')->all();

    editeur()->set('domain_ids', array_slice($ids, 0, 3))->call('toggleDomain', $ids[3])
        ->assertHasErrors(['domain_ids'])
        ->assertCount('domain_ids', 3)
        ->call('toggleDomain', $ids[0])
        ->assertCount('domain_ids', 2);
});

it('refuse un domaine désactivé ou inconnu, mais laisse celui qu’on avait déjà', function () {
    $domaine = Domain::query()->where('name', 'Sécurité')->firstOrFail();
    $user = person('Awa', domains: ['Sécurité']);
    $domaine->update(['active' => false]);

    // Déjà choisi : il reste.
    editeur($user)->set('bio', 'Mise à jour')->call('save')->assertHasNoErrors();
    expect($user->profile->domains()->pluck('domains.name')->all())->toBe(['Sécurité']);

    // Une autre personne ne peut pas le choisir.
    editeur()->set('domain_ids', [$domaine->id])->call('save')->assertHasErrors(['domain_ids']);
    editeur()->set('domain_ids', [(string) Str::uuid()])->call('save')->assertHasErrors(['domain_ids']);
});

it('vide l’ancienne ville quand on change de pays', function () {
    $ville = chercherVille('Bouaké');

    editeur()->set('country_id', $ville->country_id)->set('city_id', $ville->id)
        ->set('country_id', '')
        ->assertSet('city_id', '');
});

it('refuse une ville qui n’est pas du pays choisi, ou sans pays', function () {
    $autre = Country::query()->create(['name' => 'Pays d’essai']);
    $ville = chercherVille('Bouaké');
    $user = User::factory()->create();

    editeur($user)->set('country_id', $autre->id)->set('city_id', $ville->id)->call('save')->assertHasErrors(['city_id']);
    editeur($user)->set('country_id', '')->set('city_id', $ville->id)->call('save')->assertHasErrors(['city_id']);
    editeur($user)->set('country_id', $ville->country_id)->set('city_id', $ville->id)->call('save')->assertHasNoErrors();
});

it('refuse une ville ou un pays désactivé, sauf pour la personne qui l’avait déjà', function () {
    $user = person('Awa', city: 'Korhogo');
    $ville = chercherVille('Korhogo');
    $ville->update(['active' => false]);

    editeur($user)->set('bio', 'Toujours à Korhogo')->call('save')->assertHasNoErrors();
    expect($user->fresh()->profile->city_id)->toBe($ville->id);

    editeur()->set('country_id', $ville->country_id)->set('city_id', $ville->id)->call('save')->assertHasErrors(['city_id']);
});

it('refuse une présentation de plus de 280 caractères et en accepte 280', function () {
    $user = User::factory()->create();

    editeur($user)->set('bio', str_repeat('é', 281))->call('save')->assertHasErrors(['bio']);
    editeur($user)->set('bio', str_repeat('é', 280))->call('save')->assertHasNoErrors();
    expect(mb_strlen($user->fresh()->profile->bio))->toBe(280);
});

it('nettoie la présentation : espaces superflus, retours à la ligne unifiés, vide devient absent', function () {
    $user = User::factory()->create();

    editeur($user)->set('bio', "  Bonjour   à \r\n tous \n\n\n\n Merci  ")->call('save')->assertHasNoErrors();
    expect($user->fresh()->profile->bio)->toBe("Bonjour à\ntous\n\nMerci");

    editeur($user)->set('bio', "   \n  ")->call('save');
    expect($user->fresh()->profile->bio)->toBeNull();
});

it('refuse une visibilité inconnue', function () {
    editeur()->set('visibility', 'secret')->call('save')->assertHasErrors(['visibility']);
});

it('prend « tout le monde » par défaut (D-067)', function () {
    editeur()->assertSet('visibility', 'public');
    expect(User::factory()->create()->profile->visibility)->toBe('public');
});

it('modifie le nom affiché, comme dans Espace', function () {
    $user = User::factory()->create(['name' => 'Ancien Nom']);

    editeur($user)->set('name', '  Nouveau Nom ')->call('save')->assertHasNoErrors();
    expect($user->fresh()->name)->toBe('Nouveau Nom');

    editeur($user)->set('name', '   ')->call('save')->assertHasErrors(['name']);
    editeur($user)->set('name', str_repeat('a', 256))->call('save')->assertHasErrors(['name']);
    expect($user->fresh()->name)->toBe('Nouveau Nom');
});

it('tient à jour la clé de recherche par nom quand le nom change', function () {
    $user = User::factory()->create(['name' => 'Ancien']);

    editeur($user)->set('name', 'Zoé ÉMILE')->call('save');

    expect($user->fresh()->name_search)->toBe('zoe emile');
});

it('inscrit au journal le changement de nom d’un modérateur, sous son ancien nom', function () {
    $moderateur = User::factory()->moderator()->create(['name' => 'Ancien Nom']);

    editeur($moderateur)->set('name', 'Nouveau Nom')->call('save');

    $entree = AdminAction::query()->where('action', 'account.renamed_by_owner')->firstOrFail();
    expect($entree->actor_label)->toBe('Ancien Nom')->and($entree->payload)->toBe(['ancien' => 'Ancien Nom', 'nouveau' => 'Nouveau Nom']);
});

it('ne modifie que son propre profil, quelles que soient les valeurs envoyées', function () {
    $autre = person('Autre', ['Couture']);
    $moi = User::factory()->create();

    editeur($moi)->set('offered', ['Cuisine'])->call('save');

    expect($autre->fresh()->profile->offeredSkills->pluck('name')->all())->toBe(['Couture'])
        ->and($moi->fresh()->profile->offeredSkills->pluck('name')->all())->toBe(['Cuisine']);
});

it('n’affiche jamais l’adresse e-mail dans l’écran du profil', function () {
    $user = User::factory()->create(['email' => 'secret@example.test']);

    editeur($user)->assertDontSee('secret@example.test');
});

it('reste à dix capacités après deux enregistrements successifs', function () {
    $user = User::factory()->create();
    $dix = collect(range(1, 10))->map(fn ($i) => "Capacité $i")->all();

    (new ProfileEditor)->save($user, ['name' => $user->name, 'bio' => null, 'country_id' => null, 'city_id' => null, 'visibility' => 'public', 'offered' => $dix, 'wanted' => [], 'domain_ids' => []]);
    (new ProfileEditor)->save($user, ['name' => $user->name, 'bio' => null, 'country_id' => null, 'city_id' => null, 'visibility' => 'public', 'offered' => array_reverse($dix), 'wanted' => [], 'domain_ids' => []]);

    expect($user->profile->offeredSkills()->count())->toBe(10);
});

it('ne plante jamais sur un identifiant falsifié : il est refusé à l’enregistrement, ignoré à l’affichage', function () {
    editeur()->set('country_id', "x' or '1'='1")->assertOk()->call('save')->assertHasErrors(['country_id']);
    editeur()->set('domain_ids', ['pas-un-identifiant'])->assertOk();
    editeur()->set('domain_ids', ['pas-un-identifiant'])->call('save')->assertHasErrors(['domain_ids']);
    editeur()->set('domain_ids', [['tableau']])->call('save')->assertHasErrors(['domain_ids']);
    editeur()->set('country_id', chercherVille('Bouaké')->country_id)->set('city_id', 'garbage')->call('save')->assertHasErrors(['city_id']);
});
