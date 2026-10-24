<?php

use App\Enums\NeedState;
use App\Enums\PublicationType;
use App\Livewire\PublierPublication;
use App\Models\City;
use App\Models\Domain;
use App\Models\Publication;
use App\Models\Skill;
use App\Models\User;
use App\Services\Publications;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function formulaire(?User $user = null, string $slug = 'besoin')
{
    return Livewire::actingAs($user ?? User::factory()->create())->test(PublierPublication::class, ['slug' => $slug]);
}

it('propose les quatre types sur l’écran Agir, chacun menant à son formulaire', function () {
    $page = $this->actingAs(User::factory()->create(['name' => 'Yao Kouassi']))->get('/agir')->assertOk()
        ->assertSeeText('Que voulez-vous faire avancer ?')
        ->assertSeeText('Dites ce qui vous manque.')
        ->assertSeeText('Vous publiez en tant que vous-même (Yao Kouassi).')->assertDontSeeText('au nom d’une ZUMRA');

    foreach (['J’ai besoin' => 'besoin', 'Je partage un savoir' => 'savoir', 'Je veux apprendre' => 'apprendre', 'Je propose mon aide' => 'aide'] as $libelle => $slug) {
        $page->assertSee('href="'.route('agir.publier', $slug).'"', false);
        $this->actingAs(User::factory()->create())->get("/agir/$slug")->assertOk();
    }
});

it('ne propose pas, dans Agir, les types de projet ni l’Appel à rejoindre, qui se publient au nom d’une ZUMRA ou d’un projet', function () {
    expect(PublicationType::forPerson())->toHaveCount(6)->and(PublicationType::cases())->toHaveCount(9);

    $this->actingAs(User::factory()->create())->get('/agir')->assertDontSeeText('Avancée de projet')->assertDontSeeText('Appel à rejoindre');
    $this->actingAs(User::factory()->create())->get('/agir/projet')->assertNotFound();
    $this->actingAs(User::factory()->create())->get('/agir/avancee')->assertNotFound();
    $this->actingAs(User::factory()->create())->get('/agir/realisation')->assertNotFound();
    $this->actingAs(User::factory()->create())->get('/agir/appel')->assertNotFound();
    $this->actingAs(User::factory()->create())->get('/agir/n-importe-quoi')->assertNotFound();

    expect(fn () => DB::table('publications')->insert([
        'id' => (string) Str::uuid(), 'author_id' => User::factory()->create()->id, 'type' => 'project_update',
        'body' => 'x', 'visibility' => 'public', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('publie chacun des quatre types ; seul le besoin porte un état, d’abord « ouvert »', function () {
    foreach ([PublicationType::Need, PublicationType::Knowledge, PublicationType::LearningWish, PublicationType::Offer] as $type) {
        $auteur = User::factory()->create();

        formulaire($auteur, $type->slug())->set('body', 'Un texte.')->call('publish')->assertHasNoErrors()->assertRedirect();

        $publication = Publication::query()->where('author_id', $auteur->id)->firstOrFail();
        expect($publication->type)->toBe($type)
            ->and($publication->need_state)->toBe($type->isNeed() ? NeedState::Open : null)
            ->and($publication->visibility)->toBe('public');
    }
});

it('affiche la publication à son auteur après l’avoir publiée', function () {
    $auteur = User::factory()->create();

    $composant = formulaire($auteur)->set('body', 'Je cherche de l’aide.')->call('publish');
    $publication = Publication::query()->where('author_id', $auteur->id)->firstOrFail();

    $composant->assertRedirect(route('publications.show', $publication));
    $this->actingAs($auteur)->withSession(['status' => 'Publié. Elle apparaît dans le Fil.'])->get(route('publications.show', $publication))
        ->assertOk()->assertSeeText('Mon besoin')->assertSeeText('Je cherche de l’aide.')->assertSeeText('Publié. Elle apparaît dans le Fil.');
});

it('exige un texte, de 500 caractères au plus', function () {
    $auteur = User::factory()->create();

    formulaire($auteur)->set('body', '')->call('publish')->assertHasErrors(['body']);
    formulaire($auteur)->set('body', "   \n  \t ")->call('publish')->assertHasErrors(['body']);
    formulaire($auteur)->set('body', str_repeat('é', 501))->call('publish')->assertHasErrors(['body']);
    expect(Publication::query()->count())->toBe(0);

    formulaire($auteur)->set('body', str_repeat('é', 500))->call('publish')->assertHasNoErrors();
    expect(mb_strlen(Publication::query()->firstOrFail()->body))->toBe(500);
});

it('nettoie le texte : espaces superflus, retours à la ligne, caractères invisibles', function () {
    $publication = publication(User::factory()->create(), data: ['body' => "  Bonjour   à \r\n tous \n\n\n\n Merci\u{200B} "]);

    expect($publication->body)->toBe("Bonjour à\ntous\n\nMerci");
});

it('garde le texte brut : ni HTML, ni lien cliquable, ni image', function () {
    $auteur = User::factory()->create();
    publication($auteur, data: ['body' => 'Voir <b>ici</b> https://exemple.com/page <img src=x onerror=alert(1)> et www.exemple.org']);

    $html = $this->actingAs(User::factory()->create())->get('/fil')->assertOk()->getContent();
    preg_match('/<p class="whitespace-pre-line[^>]*>(.*?)<\/p>/s', $html, $texte);

    expect($texte[1])->toContain('&lt;b&gt;ici&lt;/b&gt;')->toContain('https://exemple.com/page')->toContain('www.exemple.org')
        ->not->toContain('<a ')->not->toContain('<img')->not->toContain('<b>');
    expect($html)->not->toContain('href="https://exemple.com')->not->toContain('href="www.exemple.org')->not->toContain('<img src=x');

    // Même chose sur la page de la publication, vue d'un visiteur sans compte.
    $page = $this->get(route('publications.show', Publication::query()->firstOrFail()))->assertOk()->getContent();
    expect($page)->not->toContain('href="https://exemple.com')->not->toContain('<img src=x');
});

it('refuse un domaine ou une ville inconnus, désactivés ou falsifiés, et accepte les valides', function () {
    $auteur = User::factory()->create();
    $domaine = Domain::query()->firstOrFail();
    $ville = City::query()->where('name', 'Bouaké')->firstOrFail();

    foreach (['garbage', (string) Str::uuid(), "1' or '1'='1"] as $faux) {
        formulaire($auteur)->set('body', 'x')->set('domain_id', $faux)->call('publish')->assertHasErrors(['domain_id']);
        formulaire($auteur)->set('body', 'x')->set('city_id', $faux)->call('publish')->assertHasErrors(['city_id']);
    }

    $domaine->update(['active' => false]);
    $ville->update(['active' => false]);
    formulaire($auteur)->set('body', 'x')->set('domain_id', $domaine->id)->call('publish')->assertHasErrors(['domain_id']);
    formulaire($auteur)->set('body', 'x')->set('city_id', $ville->id)->call('publish')->assertHasErrors(['city_id']);
    $domaine->update(['active' => true]);
    $ville->update(['active' => true]);

    formulaire($auteur)->set('body', 'x')->set('domain_id', $domaine->id)->set('city_id', $ville->id)->call('publish')->assertHasNoErrors();
    $publication = Publication::query()->firstOrFail();
    expect($publication->domain_id)->toBe($domaine->id)->and($publication->city_id)->toBe($ville->id);
});

it('reprend la ville et la visibilité du profil, qu’on peut changer pour la publication', function () {
    $auteur = person('Awa Traoré', ['Couture'], city: 'Korhogo', visibility: 'members');

    formulaire($auteur)->assertSet('city_id', City::query()->where('name', 'Korhogo')->value('id'))->assertSet('visibility', 'members');
    formulaire(User::factory()->create())->assertSet('city_id', '')->assertSet('visibility', 'public');

    formulaire($auteur)->set('body', 'x')->set('visibility', 'public')->set('city_id', '')->call('publish')->assertHasNoErrors();
    $publication = Publication::query()->firstOrFail();
    expect($publication->visibility)->toBe('public')->and($publication->city_id)->toBeNull();
});

it('refuse une visibilité inconnue', function () {
    formulaire()->set('body', 'x')->set('visibility', 'secret')->call('publish')->assertHasErrors(['visibility']);
});

it('accepte trois capacités au plus, de 2 à 60 caractères, partagées sans doublon', function () {
    $auteur = User::factory()->create();

    formulaire($auteur)->set('body', 'x')->set('skills', ['Excel', 'Laravel', 'Couture', 'Cuisine'])->call('publish')->assertHasErrors(['skills']);
    formulaire($auteur)->set('body', 'x')->set('skills', ['a'])->call('publish')->assertHasErrors(['skills']);
    formulaire($auteur)->set('body', 'x')->set('skills', [str_repeat('é', 61)])->call('publish')->assertHasErrors(['skills']);
    expect(Publication::query()->count())->toBe(0);

    person('Awa', ['Comptabilité']);
    formulaire($auteur)->set('body', 'x')->set('skills', ['COMPTABILITE', 'comptabilité', 'Tableur Excel', 'Laravel'])->call('publish')->assertHasNoErrors();

    $publication = Publication::query()->where('author_id', $auteur->id)->firstOrFail();
    expect($publication->skills->pluck('name')->all())->toBe(['Comptabilité', 'Tableur Excel', 'Laravel'])
        ->and(Skill::query()->where('normalized', 'comptabilite')->count())->toBe(1);
});

it('refuse d’ajouter une quatrième capacité depuis l’écran et le dit', function () {
    formulaire()->set('skills', ['Excel', 'Laravel', 'Couture'])->set('skillsInput', 'Cuisine')->call('addSkill', 'skills')
        ->assertHasErrors(['skillsInput'])->assertCount('skills', 3)->assertSeeText('Trois capacités au plus');
});

it('propose les capacités déjà utilisées, par un profil ou par une publication', function () {
    person('Awa', ['Couture de fête']);
    publication(User::factory()->create(), data: ['skills' => ['Couture sur mesure']]);

    formulaire()->set('skillsInput', 'coutu')->assertSeeText('Couture de fête')->assertSeeText('Couture sur mesure')->assertSeeText('Ajouter « coutu »');
});

it('ne crée aucune capacité tant qu’on n’a pas publié', function () {
    formulaire()->set('skillsInput', 'Capacité abandonnée')->call('addSkill', 'skills');

    expect(Skill::query()->where('normalized', 'capacite abandonnee')->exists())->toBeFalse();
});

it('refuse de publier à une personne dont l’adresse e-mail n’est pas vérifiée', function () {
    $nonVerifie = User::factory()->unverified()->create();

    $this->actingAs($nonVerifie)->get('/agir')->assertRedirect();
    expect(fn () => app(Publications::class)->publish($nonVerifie, PublicationType::Need, ['body' => 'x']))
        ->toThrow(AuthorizationException::class)
        ->and(Publication::query()->count())->toBe(0);
});

it('ne laisse publier que sous son propre nom', function () {
    $auteur = User::factory()->create();
    $autre = User::factory()->create();

    $publication = app(Publications::class)->publish($auteur, PublicationType::Need, ['body' => 'x', 'author_id' => $autre->id]);

    expect($publication->author_id)->toBe($auteur->id);
});

it('limite le nombre de publications par heure et par jour, par personne, avec un message clair', function () {
    config(['gamad.limits.publications_per_hour' => 2, 'gamad.limits.publications_per_day' => 3]);
    $auteur = User::factory()->create();

    publication($auteur);
    publication($auteur);
    expect(fn () => publication($auteur))->toThrow(ValidationException::class);
    formulaire($auteur)->set('body', 'x')->call('publish')->assertHasErrors(['body'])->assertSeeText('Vous publiez trop vite');
    expect(Publication::query()->count())->toBe(2);

    // Une autre personne n'est pas touchée.
    publication(User::factory()->create());
    expect(Publication::query()->count())->toBe(3);
});

it('n’affiche aucun compteur d’approbation sur l’écran de publication', function () {
    formulaire()->assertSeeText('Aucun chiffre d’approbation n’est affiché.')->assertDontSeeText('j’aime');
});

it('garde la règle d’autorisation de publier côté serveur', function () {
    expect(Gate::forUser(User::factory()->create())->allows('create', Publication::class))->toBeTrue()
        ->and(Gate::forUser(User::factory()->unverified()->create())->allows('create', Publication::class))->toBeFalse()
        ->and(Gate::forUser(null)->allows('create', Publication::class))->toBeFalse();
});
