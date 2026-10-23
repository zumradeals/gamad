<?php

use App\Enums\PlatformRole;
use App\Livewire\ParametresTextes;
use App\Models\AdminAction;
use App\Models\User;
use App\Services\LegalTexts;
use App\Services\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->president = User::factory()->superAdmin()->create(['name' => 'Le Président']);
    $this->moderateur = User::factory()->moderator()->create();
    $this->personne = User::factory()->create(['name' => 'Awa Traoré']);
});

function mentions_completes(User $qui): void
{
    app(LegalTexts::class)->saveLegalValues($qui, [
        'legal_publisher' => 'Association GAMAD',
        'legal_director' => 'Koné Djakaridja',
        'legal_contact' => 'contact@gamad.test',
        'legal_host' => "Contabo GmbH\nAachener Str. 1, Munich",
    ]);
}

// ─── Charte de conduite ──────────────────────────────────────────────────────

it('n’existe pas pour le public tant que le Président n’a pas adopté la charte', function () {
    $this->get(route('charte'))->assertNotFound();
    $this->actingAs($this->personne)->get(route('charte'))->assertNotFound();
    $this->actingAs($this->moderateur)->get(route('charte'))->assertNotFound();
});

it('montre au seul Président le projet de charte, marqué comme non adopté', function () {
    $this->actingAs($this->president)->get(route('charte'))->assertOk()->assertSeeText('Charte de conduite')->assertSeeText('Projet de charte, non adopté')
        ->assertSeeText('Toutes les croyances sont respectées')->assertDontSeeText('Adoptée le');
});

it('publie la charte une fois adoptée, avec sa date, sans le marquage de projet, pour un visiteur comme pour une personne connectée', function () {
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(12, 0));
    app(LegalTexts::class)->adoptCharter($this->president);

    $this->get(route('charte'))->assertOk()->assertSeeText('Charte de conduite')->assertSeeText('Adoptée le 5 novembre 2026')->assertDontSeeText('Projet de charte')
        ->assertSeeText('Il n’y a pas de compteur de « j’aime »')->assertSeeText('Signaler et modérer')->assertSeeText('Une ZUMRA religieuse a exactement les mêmes droits');
    $this->actingAs($this->personne)->get(route('charte'))->assertOk()->assertSeeText('Adoptée le 5 novembre 2026')->assertSee('aria-label="Navigation principale"', false);
});

it('n’emploie, pour les visiteurs, que la mise en page sans navigation de l’application', function () {
    app(LegalTexts::class)->adoptCharter($this->president);

    $this->get(route('charte'))->assertOk()->assertDontSee('aria-label="Navigation principale"', false)->assertSee('aria-label="GAMAD — accueil"', false)->assertDontSeeText('Espace');
});

it('cesse d’être publique quand le Président retire l’adoption', function () {
    app(LegalTexts::class)->adoptCharter($this->president);
    $this->get(route('charte'))->assertOk();

    app(LegalTexts::class)->withdrawCharter($this->president);

    expect(app(LegalTexts::class)->charterAdopted())->toBeFalse();
    $this->get(route('charte'))->assertNotFound();
});

// ─── Mentions légales ────────────────────────────────────────────────────────

it('n’existent pas pour le public tant qu’elles ne sont pas adoptées, et le Président les lit en projet', function () {
    $this->get(route('mentions'))->assertNotFound();
    $this->actingAs($this->personne)->get(route('mentions'))->assertNotFound();

    $this->actingAs($this->president)->get(route('mentions'))->assertOk()->assertSeeText('Projet de mentions légales, non adopté')->assertSeeText('[à compléter]');
});

it('publient les champs renseignés une fois adoptées', function () {
    mentions_completes($this->president);
    app(LegalTexts::class)->adoptLegal($this->president);

    $this->get(route('mentions'))->assertOk()->assertSeeText('Mentions légales')->assertSeeText('Association GAMAD')->assertSeeText('Koné Djakaridja')->assertSeeText('contact@gamad.test')
        ->assertSeeText('Contabo GmbH')->assertSeeText('Aachener Str. 1, Munich')->assertDontSeeText('[à compléter]')->assertDontSeeText('Projet de mentions')
        ->assertSeeText('Il n’y a ni publicité ni suivi de votre navigation')->assertSeeText('supprimer votre compte');
});

it('ne sont pas publiques si un champ a été vidé après l’adoption', function () {
    mentions_completes($this->president);
    app(LegalTexts::class)->adoptLegal($this->president);

    app(LegalTexts::class)->saveLegalValues($this->president, ['legal_host' => '']);

    expect(app(LegalTexts::class)->legalAdopted())->toBeFalse();
    $this->get(route('mentions'))->assertNotFound();
    $this->actingAs($this->president)->get(route('mentions'))->assertOk()->assertSeeText('[à compléter]');
});

it('cessent d’être publiques quand le Président retire l’adoption des mentions légales', function () {
    mentions_completes($this->president);
    app(LegalTexts::class)->adoptLegal($this->president);
    $this->get(route('mentions'))->assertOk();

    app(LegalTexts::class)->withdrawLegal($this->president);

    expect(app(LegalTexts::class)->legalAdoptedAt())->toBeNull()->and(app(LegalTexts::class)->legalAdopted())->toBeFalse();
    $this->get(route('mentions'))->assertNotFound();
    $this->get('/')->assertDontSee(route('mentions'), false);
});

it('ne laissent adopter les mentions légales qu’une fois les quatre champs renseignés', function () {
    expect(fn () => app(LegalTexts::class)->adoptLegal($this->president))->toThrow(ValidationException::class, 'Renseignez d’abord');

    app(LegalTexts::class)->saveLegalValues($this->president, ['legal_publisher' => 'Association GAMAD', 'legal_director' => 'Koné Djakaridja', 'legal_contact' => 'contact@gamad.test']);
    expect(fn () => app(LegalTexts::class)->adoptLegal($this->president))->toThrow(ValidationException::class);
    expect(app(LegalTexts::class)->legalAdoptedAt())->toBeNull();

    app(LegalTexts::class)->saveLegalValues($this->president, ['legal_host' => 'Contabo GmbH']);
    app(LegalTexts::class)->adoptLegal($this->president);
    expect(app(LegalTexts::class)->legalAdopted())->toBeTrue();
});

// ─── Droits et journal ───────────────────────────────────────────────────────

it('réserve l’adoption et les champs au super administrateur, y compris à un modérateur', function () {
    foreach ([$this->personne, $this->moderateur] as $qui) {
        expect(fn () => app(LegalTexts::class)->adoptCharter($qui))->toThrow(AuthorizationException::class);
        expect(fn () => app(LegalTexts::class)->withdrawCharter($qui))->toThrow(AuthorizationException::class);
        expect(fn () => app(LegalTexts::class)->adoptLegal($qui))->toThrow(AuthorizationException::class);
        expect(fn () => app(LegalTexts::class)->withdrawLegal($qui))->toThrow(AuthorizationException::class);
        expect(fn () => app(LegalTexts::class)->saveLegalValues($qui, ['legal_host' => 'x']))->toThrow(AuthorizationException::class);
    }
    expect(app(LegalTexts::class)->charterAdopted())->toBeFalse()->and(AdminAction::count())->toBe(0);
});

it('inscrit au journal d’administration l’adoption, son retrait et les champs, avec leur auteur', function () {
    mentions_completes($this->president);
    app(LegalTexts::class)->adoptCharter($this->president);
    app(LegalTexts::class)->adoptLegal($this->president);
    app(LegalTexts::class)->withdrawCharter($this->president);

    $clefs = AdminAction::query()->where('action', 'setting.changed')->pluck('subject_id')->all();
    foreach (['legal_publisher', 'legal_director', 'legal_contact', 'legal_host', 'charter_adopted_at', 'legal_adopted_at'] as $clef) {
        expect($clefs)->toContain($clef);
    }
    expect(AdminAction::where('subject_id', 'charter_adopted_at')->count())->toBe(2)->and(AdminAction::where('actor_id', '!=', $this->president->id)->count())->toBe(0);
});

// ─── Écran du Président ──────────────────────────────────────────────────────

it('ferme l’écran des textes à toute personne autre que le super administrateur', function () {
    $this->actingAs($this->personne)->get(route('espace.parametres.textes'))->assertForbidden();
    $this->actingAs($this->moderateur)->get(route('espace.parametres.textes'))->assertForbidden();
    app('auth')->forgetGuards();
    $this->get(route('espace.parametres.textes'))->assertRedirect(route('login'));
    $this->actingAs($this->president)->get(route('espace.parametres.textes'))->assertOk()->assertSeeText('Charte et mentions légales')->assertSeeText('Projet : non adopté');
});

it('relie l’écran des textes depuis Paramètres et Espace', function () {
    $this->actingAs($this->president)->get(route('espace.parametres'))->assertOk()->assertSee(route('espace.parametres.textes'), false);
    $this->actingAs($this->president)->get(route('espace'))->assertOk()->assertSeeText('charte et mentions légales');
    $this->actingAs($this->personne)->get(route('espace'))->assertOk()->assertDontSeeText('charte et mentions légales');
});

it('adopte et retire la charte depuis l’écran', function () {
    Livewire::actingAs($this->president)->test(ParametresTextes::class)
        ->call('adoptCharter')->assertSeeText('Charte adoptée')->assertSeeText('Elle est publique')
        ->call('withdrawCharter')->assertSeeText('Adoption de la charte retirée')->assertSeeText('Projet : non adopté');

    expect(app(LegalTexts::class)->charterAdopted())->toBeFalse();
});

it('enregistre les champs des mentions légales, vérifie l’adresse e-mail, et adopte une fois complets', function () {
    Livewire::actingAs($this->president)->test(ParametresTextes::class)
        ->set('publisher', 'Association GAMAD')->set('director', 'Koné Djakaridja')->set('contact', 'pas-une-adresse')->set('host', 'Contabo GmbH')
        ->call('saveLegal')->assertHasErrors(['contact'])
        ->set('contact', 'contact@gamad.test')->call('saveLegal')->assertHasNoErrors()->assertSeeText('Mentions légales enregistrées')
        ->call('adoptLegal')->assertSeeText('Mentions légales adoptées')->assertSeeText('Elles sont publiques');

    expect(app(LegalTexts::class)->legalAdopted())->toBeTrue();
    $this->get(route('mentions'))->assertOk()->assertSeeText('Association GAMAD');
});

it('refuse depuis l’écran d’adopter des mentions incomplètes, avec le message', function () {
    Livewire::actingAs($this->president)->test(ParametresTextes::class)->call('adoptLegal')->assertHasErrors(['legal'])->assertSeeText('Renseignez d’abord');
    expect(app(LegalTexts::class)->legalAdoptedAt())->toBeNull();
});

it('refuse les actions de l’écran à une personne qui n’est pas super administrateur, même avec l’écran ouvert', function () {
    $c = Livewire::actingAs($this->president)->test(ParametresTextes::class);
    $this->president->forceFill(['platform_role' => PlatformRole::Member])->save();

    $c->call('adoptCharter')->assertForbidden();
    expect(app(LegalTexts::class)->charterAdopted())->toBeFalse();
});

// ─── Inscription et acceptation de la charte ─────────────────────────────────

function inscription(array $plus = []): TestResponse
{
    return test()->post('/register', $plus + ['name' => 'Nouvelle Personne', 'email' => 'nouvelle@example.com', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1']);
}

it('ne demande rien à l’inscription tant que la charte n’est pas adoptée, et ne garde aucune date', function () {
    $this->get(route('register'))->assertOk()->assertDontSee('name="charter"', false);

    inscription()->assertRedirect(route('fil'));

    expect(User::where('email', 'nouvelle@example.com')->sole()->charter_accepted_at)->toBeNull();
});

it('demande d’accepter la charte à l’inscription une fois adoptée, avec un lien vers elle', function () {
    app(LegalTexts::class)->adoptCharter($this->president);

    $this->get(route('register'))->assertOk()->assertSee('name="charter"', false)->assertSeeText('J’ai lu et j’accepte la charte de conduite')->assertSee(route('charte'), false);
});

it('refuse l’inscription sans accepter la charte adoptée, avec le message', function () {
    app(LegalTexts::class)->adoptCharter($this->president);

    inscription()->assertSessionHasErrors(['charter']);
    expect(User::where('email', 'nouvelle@example.com')->exists())->toBeFalse();

    $this->followingRedirects()->from(route('register'))->post('/register', ['name' => 'Nouvelle Personne', 'email' => 'nouvelle@example.com', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1'])
        ->assertSeeText('Acceptez la charte de conduite pour créer un compte.')->assertSee('aria-invalid="true"', false);
});

it('enregistre la date d’acceptation de la charte à l’inscription', function () {
    app(LegalTexts::class)->adoptCharter($this->president);
    $this->travelTo(now()->setDate(2026, 11, 6)->setTime(9, 30));

    inscription(['charter' => '1'])->assertRedirect(route('fil'));

    expect(User::where('email', 'nouvelle@example.com')->sole()->charter_accepted_at->format('Y-m-d H:i'))->toBe('2026-11-06 09:30');
});

it('ne touche pas aux personnes déjà inscrites avant l’adoption de la charte', function () {
    app(LegalTexts::class)->adoptCharter($this->president);

    expect($this->personne->fresh()->charter_accepted_at)->toBeNull();
    $this->actingAs($this->personne)->get(route('fil'))->assertOk();
});

// ─── Pied de page ────────────────────────────────────────────────────────────

it('n’affiche aucun lien de texte tant que rien n’est adopté', function () {
    foreach ([$this->get('/'), $this->get(route('login')), $this->actingAs($this->personne)->get(route('espace'))] as $reponse) {
        $reponse->assertOk()->assertDontSee(route('charte'), false)->assertDontSee(route('mentions'), false)->assertDontSee('aria-label="Textes de GAMAD"', false);
    }
});

it('affiche dans le pied de page les seuls textes adoptés, pour les visiteurs et les personnes connectées', function () {
    app(LegalTexts::class)->adoptCharter($this->president);

    foreach ([$this->get('/'), $this->get(route('login'))] as $reponse) {
        $reponse->assertSeeText('Charte de conduite')->assertSee(route('charte'), false)->assertDontSeeText('Mentions légales');
    }
    $this->actingAs($this->personne)->get(route('espace'))->assertSeeText('Charte de conduite')->assertDontSeeText('Mentions légales');

    app('auth')->forgetGuards();
    mentions_completes($this->president);
    app(LegalTexts::class)->adoptLegal($this->president);
    $this->get('/')->assertSeeText('Charte de conduite')->assertSeeText('Mentions légales')->assertSee(route('mentions'), false);
});

it('retire du pied de page le lien des mentions légales dont un champ a été vidé après l’adoption', function () {
    mentions_completes($this->president);
    app(LegalTexts::class)->adoptLegal($this->president);
    $this->get('/')->assertSee(route('mentions'), false);

    app(LegalTexts::class)->saveLegalValues($this->president, ['legal_contact' => '']);

    $this->get('/')->assertDontSee(route('mentions'), false)->assertDontSeeText('Mentions légales');
});

it('lit un réglage de texte adopté sans le journaliser une seconde fois par lecture', function () {
    app(LegalTexts::class)->adoptCharter($this->president);
    $avant = AdminAction::count();

    app(LegalTexts::class)->charterAdopted();
    app(Settings::class)->get('charter_adopted_at');

    expect(AdminAction::count())->toBe($avant);
});
