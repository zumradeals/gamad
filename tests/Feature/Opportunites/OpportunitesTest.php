<?php

use App\Livewire\OpportunityCatalog;
use App\Livewire\OpportunityForm;
use App\Livewire\OpportunityManager;
use App\Livewire\OpportunityResponses;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Domain;
use App\Models\Opportunity;
use App\Models\OpportunityResponse;
use App\Models\PersonConnection;
use App\Models\Publication;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Opportunities;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function entrepriseOpp(string $name = 'Orange Test', ?User $owner = null, bool $verified = true, string $status = ''): Company
{
    $company = new Company(['name' => $name, 'legal_name' => $name.' SA', 'registration_country' => 'CI', 'registration_number' => 'RCCM-'.Str::random(10),
        'sector' => 'Télécommunications', 'city' => 'Abidjan', 'summary' => 'Une entreprise de test.']);
    $company->slug = Str::slug($name).'-'.strtolower(Str::random(8));
    $company->forceFill([
        'owner_id' => ($owner ?? User::factory()->create())->id,
        'status' => $status ?: ($verified ? Company::VERIFIED : 'pending'),
        'verified_at' => $verified ? now() : null,
    ])->save();

    return $company;
}

function pdfOpp(string $name = 'cv.pdf', int $kb = 20): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".str_repeat('x', $kb * 100)."\n%%EOF");
}

beforeEach(function () {
    Storage::fake('local');
    $this->company = entrepriseOpp();
    $this->owner = $this->company->owner;
});

// ─── Catalogue ──────────────────────────────────────────────────────────────

it('ouvre le catalogue à un visiteur sans compte et affiche seulement les opportunités ouvertes d’entreprises vérifiées', function () {
    opportunite($this->company, ['title' => 'Ingénieur réseau']);
    opportunite($this->company, ['title' => 'Brouillon secret', 'status' => 'draft']);
    opportunite($this->company, ['title' => 'Offre clôturée', 'status' => 'closed']);
    opportunite($this->company, ['title' => 'Offre archivée', 'status' => 'archived']);
    opportunite($this->company, ['title' => 'Offre expirée', 'deadline' => now('Africa/Abidjan')->subDay()->toDateString()]);
    opportunite(entrepriseOpp('Pas vérifiée', null, false), ['title' => 'Offre non vérifiée']);
    opportunite(entrepriseOpp('Suspendue', null, true, 'suspended'), ['title' => 'Offre suspendue']);

    $this->get(route('opportunites.index'))->assertOk()
        ->assertSeeText('Opportunités')->assertSeeText('Ingénieur réseau')->assertSeeText('Orange Test')
        ->assertDontSeeText('Brouillon secret')->assertDontSeeText('Offre clôturée')->assertDontSeeText('Offre archivée')
        ->assertDontSeeText('Offre expirée')->assertDontSeeText('Offre non vérifiée')->assertDontSeeText('Offre suspendue');
});

it('affiche un état vide honnête, sans exemple fictif', function () {
    $this->get(route('opportunites.index'))->assertOk()->assertSeeText('Aucune opportunité n’est publiée pour le moment.')->assertSeeText('Aucun exemple fictif');
});

it('recherche, filtre par type, localité, domaine et date, et combine les filtres', function () {
    $pays = Country::query()->firstOrCreate(['name' => 'Pays Opp']);
    $alpha = City::query()->create(['name' => 'Ville Alpha', 'country_id' => $pays->id]);
    $beta = City::query()->create(['name' => 'Ville Beta', 'country_id' => $pays->id]);
    $energie = Domain::query()->create(['name' => 'Énergie test', 'sort_order' => 1]);
    opportunite($this->company, ['title' => 'Électricien solaire', 'kind' => 'mission', 'city_id' => $alpha->id, 'domain_id' => $energie->id, 'skills' => ['Photovoltaïque']]);
    opportunite($this->company, ['title' => 'Comptable', 'kind' => 'job', 'city_id' => $beta->id]);
    opportunite($this->company, ['title' => 'Stagiaire marketing', 'kind' => 'internship', 'published_at' => now()->subDays(20)]);
    $titres = fn ($test) => $test->viewData('opportunities')->pluck('title')->sort()->values()->all();

    $t = Livewire::test(OpportunityCatalog::class);
    expect($titres($t))->toEqualCanonicalizing(['Comptable', 'Électricien solaire', 'Stagiaire marketing']);
    expect($titres($t->set('query', 'comptable')))->toEqualCanonicalizing(['Comptable']);
    expect($titres($t->set('query', 'photovolta')))->toEqualCanonicalizing(['Électricien solaire']);
    expect($titres($t->set('query', 'orange')))->toHaveCount(3);
    expect($titres($t->set('query', '')->call('setKind', 'job')))->toEqualCanonicalizing(['Comptable']);
    expect($titres($t->call('clearFilters')->set('city', 'Ville Alpha')))->toEqualCanonicalizing(['Électricien solaire']);
    expect($titres($t->set('city', 'ville ALPHA')))->toEqualCanonicalizing(['Électricien solaire'])->and($titres($t->set('city', 'alph')))->toEqualCanonicalizing(['Électricien solaire']);
    expect($titres($t->set('city', '')->set('domain', $energie->id)))->toEqualCanonicalizing(['Électricien solaire']);
    expect($titres($t->set('domain', '')->set('date', '7')))->toEqualCanonicalizing(['Comptable', 'Électricien solaire']);
    $t->call('clearFilters')->call('setKind', 'mission')->set('city', 'Ville Beta')->assertSeeText('Aucune opportunité ne correspond à cette recherche.');
    expect($titres($t->call('clearFilters')))->toHaveCount(3);
});

it('compte réellement les opportunités par type et pagine par dix', function () {
    foreach (range(1, 12) as $i) {
        opportunite($this->company, ['title' => "Poste numéro $i", 'kind' => $i <= 3 ? 'mission' : 'job', 'published_at' => now()->subMinutes($i)]);
    }
    $test = Livewire::test(OpportunityCatalog::class)->assertSeeText('12 opportunités trouvées')->assertSeeText('Poste numéro 1')->assertDontSeeText('Poste numéro 12')
        ->assertSeeText('Page 1 sur 2')->call('nextPage')->assertSeeText('Poste numéro 12')->assertDontSeeText('Poste numéro 1 ');
    expect($test->viewData('kindCounts'))->toEqual(['mission' => 3, 'job' => 9]);
});

it('trie par date limite proche sur demande', function () {
    opportunite($this->company, ['title' => 'Sans date']);
    opportunite($this->company, ['title' => 'Date proche', 'deadline' => now('Africa/Abidjan')->addDays(2)->toDateString()]);
    opportunite($this->company, ['title' => 'Date lointaine', 'deadline' => now('Africa/Abidjan')->addDays(20)->toDateString()]);
    $titles = Livewire::test(OpportunityCatalog::class)->set('sort', 'echeance')->viewData('opportunities')->pluck('title')->all();
    expect($titles)->toBe(['Date proche', 'Date lointaine', 'Sans date']);
});

it('propose des entreprises et des opportunités récentes, et des pistes explicables selon les capacités du profil', function () {
    $moi = person('Awa Traoré', ['Photovoltaïque']);
    opportunite($this->company, ['title' => 'Installateur solaire', 'skills' => ['Photovoltaïque']]);
    opportunite($this->company, ['title' => 'Comptable']);
    Livewire::actingAs($moi)->test(OpportunityCatalog::class)->assertSeeText('Entreprises qui proposent des opportunités')->assertSeeText('Opportunités récentes')
        ->assertSeeText('Selon vos capacités')->assertSeeText('Correspond à votre capacité « Photovoltaïque »')->assertDontSeeText('%');
});

// ─── Détail ─────────────────────────────────────────────────────────────────

it('affiche le détail à tous et masque brouillon et archive au public', function () {
    $o = opportunite($this->company, ['title' => 'Chef de chantier', 'missions' => "Planifier les travaux\nContrôler la qualité", 'skills' => ['Chantier'], 'work_mode' => 'hybrid', 'workload' => 'full_time']);
    $this->get(route('opportunites.show', $o))->assertOk()->assertSeeText('Chef de chantier')->assertSeeText('Planifier les travaux')->assertSeeText('Hybride')
        ->assertSeeText('Temps plein')->assertSeeText('Se connecter pour répondre')->assertSee(route('entreprises.show', $this->company), false)->assertSeeText($o->reference);

    foreach (['draft', 'archived'] as $status) {
        $hidden = opportunite($this->company, ['status' => $status]);
        $this->get(route('opportunites.show', $hidden))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('opportunites.show', $hidden))->assertNotFound();
        $this->actingAs($this->owner)->get(route('opportunites.show', $hidden))->assertOk();
        auth()->logout();
    }
});

it('ne présente jamais une offre clôturée ou expirée comme ouverte', function () {
    $closed = opportunite($this->company, ['status' => 'closed']);
    $expired = opportunite($this->company, ['deadline' => now('Africa/Abidjan')->subDay()->toDateString()]);
    $this->actingAs(User::factory()->create());
    $this->get(route('opportunites.show', $closed))->assertOk()->assertSeeText('Clôturée : cette opportunité n’accepte plus de réponses.')->assertDontSee('Postuler')->assertDontSee('Je suis intéressé');
    $this->get(route('opportunites.show', $expired))->assertOk()->assertSeeText('Date limite dépassée')->assertDontSee('Postuler');
    $this->get(route('opportunites.respond', $expired))->assertForbidden();
    expect($expired->fresh()->isOpen())->toBeFalse();
});

it('masque l’opportunité d’une entreprise suspendue et ne la laisse pas au public', function () {
    $o = opportunite($this->company);
    $this->company->forceFill(['status' => 'suspended'])->save();
    $this->get(route('opportunites.show', $o))->assertNotFound();
    $this->get(route('opportunites.index'))->assertDontSeeText($o->title);
});

it('adapte le bouton d’action au type : Postuler pour un emploi ou un stage, Je suis intéressé sinon', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('opportunites.show', opportunite($this->company, ['kind' => 'job'])))->assertSeeText('Postuler');
    $this->get(route('opportunites.show', opportunite($this->company, ['kind' => 'internship'])))->assertSeeText('Postuler');
    $this->get(route('opportunites.show', opportunite($this->company, ['kind' => 'mission'])))->assertSeeText('Je suis intéressé');
    $this->get(route('opportunites.show', opportunite($this->company, ['kind' => 'collaboration'])))->assertSeeText('Je suis intéressé');
});

it('sécurise le lien externe de candidature', function () {
    $o = opportunite($this->company, ['apply_mode' => 'external', 'external_url' => 'https://exemple.ci/postuler']);
    $this->actingAs(User::factory()->create())->get(route('opportunites.show', $o))->assertSee('href="https://exemple.ci/postuler"', false)->assertSee('rel="noopener noreferrer nofollow"', false);
    $this->get(route('opportunites.respond', $o))->assertForbidden();

    foreach (['http://exemple.ci', 'javascript:alert(1)', 'https://localhost/x', 'https://user:pw@exemple.ci', 'https://127.0.0.1/x', 'exemple.ci', 'https://intranet'] as $bad) {
        expect(fn () => app(Opportunities::class)->safeUrl($bad))->toThrow(ValidationException::class);
    }
});

// ─── Publication ────────────────────────────────────────────────────────────

it('réserve la publication au propriétaire d’une entreprise : refus d’une personne physique et d’un tiers', function () {
    $personne = User::factory()->create();
    $this->get(route('opportunites.create', $this->company))->assertRedirect(route('login'));
    $this->actingAs($personne)->get(route('opportunites.create', $this->company))->assertForbidden();
    $this->actingAs($personne)->get(route('entreprises.opportunities', $this->company))->assertForbidden();
    Livewire::actingAs($personne)->test(OpportunityForm::class, ['company' => $this->company])->assertForbidden();

    $o = opportunite($this->company);
    $this->actingAs($personne)->get(route('opportunites.edit', $o))->assertForbidden();
    $this->actingAs($personne)->get(route('opportunites.responses', $o))->assertForbidden();
    expect(Opportunity::count())->toBe(1);
});

it('publie en trois étapes avec aperçu, puis apparaît sur la page entreprise et dans le catalogue (une seule source)', function () {
    $city = City::query()->create(['name' => 'Ville Alpha', 'country_id' => Country::query()->firstOrCreate(['name' => 'Pays Opp'])->id]);
    $test = Livewire::actingAs($this->owner)->test(OpportunityForm::class, ['company' => $this->company])
        ->assertSeeText('Que propose votre entreprise ?')->assertSet('step', 1)
        ->call('next')->assertHasErrors(['kind', 'title', 'description'])->assertSet('step', 1)
        ->call('chooseKind', 'job')->set('title', 'Technicien de maintenance')->set('description', 'Assurer la maintenance préventive des équipements.')
        ->call('next')->assertHasNoErrors()->assertSet('step', 2)
        ->set('city_id', $city->id)->set('skillsText', 'Électricité, Maintenance, Électricité')->set('missions', "Visiter les sites\nRédiger les rapports")
        ->call('back')->assertSet('step', 1)->assertSet('title', 'Technicien de maintenance')
        ->call('next')->assertSet('city_id', $city->id)
        ->call('preview')->assertSet('step', 3)->assertSeeText('Aperçu de votre opportunité')->assertSeeText('Technicien de maintenance')->assertSeeText('Visiter les sites')->assertSeeText('Publier l’opportunité');
    expect(Opportunity::count())->toBe(0);

    $test->call('publish')->assertHasNoErrors()->assertRedirect();
    $o = Opportunity::sole();
    expect($o->status)->toBe('published')->and($o->published_at)->not->toBeNull()->and($o->skills)->toBe(['Électricité', 'Maintenance'])
        ->and($o->company_id)->toBe($this->company->id)->and($o->author_id)->toBe($this->owner->id)->and($o->reference)->toStartWith('OPP-');

    $this->get(route('entreprises.show', $this->company))->assertSeeText('Nos opportunités')->assertSeeText('Technicien de maintenance')->assertSee(route('opportunites.show', $o), false);
    $this->get(route('opportunites.index'))->assertSeeText('Technicien de maintenance');
    expect(Company::find($this->company->id)->opportunities()->count())->toBe(1);
});

it('refuse une date limite passée, un type invalide et des champs trop longs à la publication', function () {
    Livewire::actingAs($this->owner)->test(OpportunityForm::class, ['company' => $this->company])
        ->call('chooseKind', 'n-importe-quoi')->assertSet('kind', '')
        ->call('chooseKind', 'mission')->set('title', str_repeat('a', 161))->set('description', 'Ok')->call('next')->assertHasErrors('title')
        ->set('title', 'Mission')->call('next')->assertSet('step', 2)
        ->set('deadline', now('Africa/Abidjan')->subDay()->toDateString())->call('preview')->assertHasErrors('deadline')->assertSet('step', 2)
        ->set('deadline', '')->set('apply_mode', 'external')->set('external_url', 'http://pas-securise.ci')->call('preview')->assertHasErrors('external_url')
        ->set('apply_mode', 'gamad')->call('preview')->assertHasNoErrors()->assertSet('step', 3);
});

it('laisse une entreprise non vérifiée préparer un brouillon sans publier', function () {
    $pending = entrepriseOpp('En attente', null, false);
    $this->actingAs($pending->owner)->get(route('opportunites.create', $pending))->assertOk()->assertSeeText('pas encore vérifiée');

    Livewire::actingAs($pending->owner)->test(OpportunityForm::class, ['company' => $pending])
        ->call('chooseKind', 'job')->set('title', 'Poste à venir')->set('description', 'Plus tard.')->call('next')->call('saveDraft')->assertRedirect();
    $draft = Opportunity::sole();
    expect($draft->status)->toBe('draft')->and($draft->published_at)->toBeNull();
    $this->get(route('opportunites.index'))->assertDontSeeText('Poste à venir');

    Livewire::actingAs($pending->owner)->test(OpportunityForm::class, ['opportunity' => $draft])
        ->call('next')->call('preview')->assertDontSeeText('Publier l’opportunité')->call('publish')->assertForbidden();
    expect($draft->fresh()->status)->toBe('draft');
});

it('refuse toute action à une entreprise suspendue', function () {
    $suspended = entrepriseOpp('Suspendue', null, true, 'suspended');
    $this->actingAs($suspended->owner)->get(route('opportunites.create', $suspended))->assertForbidden();
    $this->actingAs($suspended->owner)->get(route('entreprises.opportunities', $suspended))->assertForbidden();
});

it('modifie, clôture, rouvre, archive et supprime un brouillon par le gestionnaire', function () {
    $o = opportunite($this->company, ['title' => 'À modifier']);
    Livewire::actingAs($this->owner)->test(OpportunityForm::class, ['opportunity' => $o])->assertSet('title', 'À modifier')->assertSet('step', 2)
        ->set('title', 'Modifiée')->call('preview')->call('publish')->assertHasNoErrors();
    expect($o->fresh()->title)->toBe('Modifiée')->and(Opportunity::count())->toBe(1);

    $manager = Livewire::actingAs($this->owner)->test(OpportunityManager::class, ['company' => $this->company])->assertSeeText('Modifiée');
    $manager->call('close', $o->id)->assertSeeText('Opportunité clôturée');
    expect($o->fresh()->status)->toBe('closed');
    $this->get(route('opportunites.index'))->assertDontSeeText('Modifiée');
    $manager->call('reopen', $o->id);
    expect($o->fresh()->status)->toBe('published');
    $manager->call('archive', $o->id);
    expect($o->fresh()->status)->toBe('archived');
    $this->get(route('opportunites.show', $o))->assertOk();
    auth()->logout();
    $this->get(route('opportunites.show', $o))->assertNotFound();

    $draft = opportunite($this->company, ['status' => 'draft', 'title' => 'Brouillon']);
    $this->actingAs($this->owner);
    $manager->call('deleteDraft', $draft->id);
    expect(Opportunity::find($draft->id))->toBeNull();
    // Un brouillon seul se supprime : une opportunité publiée, non.
    expect(fn () => app(Opportunities::class)->deleteDraft($this->owner, $o))->toThrow(HttpException::class);
});

it('refuse de rouvrir une opportunité dont la date limite est dépassée', function () {
    $o = opportunite($this->company, ['status' => 'closed', 'deadline' => now('Africa/Abidjan')->subDay()->toDateString()]);
    Livewire::actingAs($this->owner)->test(OpportunityManager::class, ['company' => $this->company])->call('reopen', $o->id)->assertHasErrors('deadline');
    expect($o->fresh()->status)->toBe('closed');
});

it('ne laisse pas gérer l’opportunité d’une autre entreprise', function () {
    $autre = entrepriseOpp('Autre');
    $theirs = opportunite($autre);
    $test = Livewire::actingAs($this->owner)->test(OpportunityManager::class, ['company' => $this->company]);
    expect(fn () => $test->call('close', $theirs->id))->toThrow(ModelNotFoundException::class);
    expect($theirs->fresh()->status)->toBe('published');
});

// ─── Sauvegarde ─────────────────────────────────────────────────────────────

it('sauvegarde et retire une opportunité, depuis le catalogue et le détail', function () {
    $o = opportunite($this->company, ['title' => 'À garder']);
    $moi = User::factory()->create();
    $this->actingAs($moi)->post(route('opportunites.save', $o))->assertRedirect();
    expect(DB::table('opportunity_saves')->where('user_id', $moi->id)->count())->toBe(1);
    $this->get(route('espace.opportunites'))->assertOk()->assertSeeText('À garder');
    $this->post(route('opportunites.save', $o));
    expect(DB::table('opportunity_saves')->count())->toBe(0);

    Livewire::actingAs($moi)->test(OpportunityCatalog::class)->call('toggleSave', $o->id)->assertSeeText('Sauvegardée')->call('toggleSave', $o->id)->assertSeeText('Sauvegarder');
    auth()->logout();
    $this->post(route('opportunites.save', $o))->assertRedirect(route('login'));
});

it('ne laisse pas sauvegarder un brouillon d’autrui', function () {
    $draft = opportunite($this->company, ['status' => 'draft']);
    $this->actingAs(User::factory()->create())->post(route('opportunites.save', $draft))->assertNotFound();
});

// ─── Réponses ───────────────────────────────────────────────────────────────

it('enregistre une candidature privée, prévient l’entreprise et refuse le doublon', function () {
    $o = opportunite($this->company, ['title' => 'Poste ouvert']);
    $candidat = person('Awa Candidate');
    $this->actingAs($candidat)->get(route('opportunites.respond', $o))->assertOk()->assertSeeText('Aucun CV n’est exigé')->assertSeeText('Votre téléphone et votre e-mail ne lui sont jamais montrés');

    $this->post(route('opportunites.respond.store', $o), ['message' => 'Je suis intéressée par ce poste.'])->assertRedirect(route('opportunites.show', $o));
    $r = OpportunityResponse::sole();
    expect($r->user_id)->toBe($candidat->id)->and($r->message)->toBe('Je suis intéressée par ce poste.')->and($r->state)->toBe('sent')->and($r->attachment_path)->toBeNull();

    $notification = UserNotification::where('user_id', $this->owner->id)->where('kind', 'opportunity_response_received')->sole();
    expect($notification->actor_id)->toBe($candidat->id)->and($notification->opportunity_id)->toBe($o->id);
    $this->actingAs($this->owner)->get(route('notifications.open', $notification))->assertRedirect(route('opportunites.responses', $o));

    // Pas de candidature en double.
    $this->actingAs($candidat)->post(route('opportunites.respond.store', $o), ['message' => 'Encore.'])->assertSessionHasErrors('message');
    expect(OpportunityResponse::count())->toBe(1);
    $this->get(route('opportunites.show', $o))->assertSeeText('Votre réponse :')->assertSeeText('Envoyée');
    $this->get(route('opportunites.respond', $o))->assertRedirect(route('opportunites.show', $o));
});

it('n’exige aucun CV et accepte une réponse sans message', function () {
    $o = opportunite($this->company, ['kind' => 'mission']);
    $this->actingAs(User::factory()->create())->post(route('opportunites.respond.store', $o), [])->assertRedirect(route('opportunites.show', $o))->assertSessionHas('status');
    expect(OpportunityResponse::sole()->message)->toBeNull();
});

it('refuse la réponse du propriétaire, d’un visiteur, sur une offre fermée, expirée ou externe', function () {
    $o = opportunite($this->company);
    $this->actingAs($this->owner)->get(route('opportunites.respond', $o))->assertForbidden();
    $this->actingAs($this->owner)->post(route('opportunites.respond.store', $o), [])->assertForbidden();
    auth()->logout();
    $this->post(route('opportunites.respond.store', $o), [])->assertRedirect(route('login'));

    $moi = User::factory()->create();
    foreach ([opportunite($this->company, ['status' => 'closed']), opportunite($this->company, ['deadline' => now('Africa/Abidjan')->subDay()->toDateString()]), opportunite($this->company, ['apply_mode' => 'external', 'external_url' => 'https://x.ci/a'])] as $closed) {
        $this->actingAs($moi)->post(route('opportunites.respond.store', $closed), ['message' => 'Allo'])->assertForbidden();
    }
    expect(OpportunityResponse::count())->toBe(0);
});

it('valide le message et la pièce jointe : PDF réel de 5 Mo au plus', function () {
    $o = opportunite($this->company);
    $moi = User::factory()->create();
    $this->actingAs($moi)->post(route('opportunites.respond.store', $o), ['message' => str_repeat('a', 1001)])->assertSessionHasErrors('message');
    $this->post(route('opportunites.respond.store', $o), ['attachment' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('attachment');
    $this->post(route('opportunites.respond.store', $o), ['attachment' => UploadedFile::fake()->createWithContent('faux.pdf', 'Ceci n’est pas un PDF')])->assertSessionHasErrors('attachment');
    $this->post(route('opportunites.respond.store', $o), ['attachment' => UploadedFile::fake()->create('gros.pdf', 6000, 'application/pdf')])->assertSessionHasErrors('attachment');
    expect(OpportunityResponse::count())->toBe(0)->and(Storage::disk('local')->allFiles('opportunity-responses'))->toBe([]);
});

it('garde la pièce jointe privée : auteur et entreprise seulement', function () {
    $o = opportunite($this->company);
    $candidat = person('Awa Privée');
    $this->actingAs($candidat)->post(route('opportunites.respond.store', $o), ['message' => 'CV joint.', 'attachment' => pdfOpp()])->assertRedirect();
    $r = OpportunityResponse::sole();
    expect($r->attachment_path)->toStartWith('opportunity-responses/')->toEndWith('.pdf');
    Storage::disk('local')->assertExists($r->attachment_path);

    $this->get(route('opportunites.attachment', $r))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($this->owner)->get(route('opportunites.attachment', $r))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('opportunites.attachment', $r))->assertNotFound();
    auth()->logout();
    $this->get(route('opportunites.attachment', $r))->assertRedirect(route('login'));
    // Jamais exposée publiquement.
    $this->get(route('opportunites.show', $o))->assertDontSee($r->attachment_path);
});

it('réserve la liste des réponses à l’entreprise et ne montre ni e-mail ni téléphone', function () {
    $o = opportunite($this->company);
    $candidat = person('Awa Discrète');
    $this->actingAs($candidat)->post(route('opportunites.respond.store', $o), ['message' => 'Bonjour, je candidate.']);

    $this->actingAs(User::factory()->create())->get(route('opportunites.responses', $o))->assertForbidden();
    $this->actingAs($candidat)->get(route('opportunites.responses', $o))->assertForbidden();
    $page = $this->actingAs($this->owner)->get(route('opportunites.responses', $o))->assertOk()->assertSeeText('Awa Discrète')->assertSeeText('Bonjour, je candidate.')->assertSeeText('Voir le profil public');
    expect($page->getContent())->not->toContain($candidat->email);
    // Ni l'opportunité publique ni le catalogue ne montrent les réponses.
    $this->get(route('opportunites.show', $o))->assertDontSeeText('Bonjour, je candidate.');
});

it('marque une réponse vue, propose un échange par une demande de relation et prévient la personne', function () {
    $o = opportunite($this->company, ['title' => 'Poste ouvert']);
    $candidat = person('Awa Contactée');
    $this->actingAs($candidat)->post(route('opportunites.respond.store', $o), ['message' => 'Bonjour.']);
    $r = OpportunityResponse::sole();

    $test = Livewire::actingAs($this->owner)->test(OpportunityResponses::class, ['opportunity' => $o])->assertSeeText('Envoyée');
    $test->call('markSeen', $r->id)->assertSeeText('Vue par l’entreprise');
    expect($r->fresh()->state)->toBe('seen')->and($r->fresh()->seen_at)->not->toBeNull();

    $test->call('contact', $r->id)->assertSeeText('Votre proposition d’échange a été envoyée');
    expect($r->fresh()->state)->toBe('contacted')->and(PersonConnection::count())->toBe(1);
    $n = UserNotification::where('user_id', $candidat->id)->where('kind', 'opportunity_response_contacted')->sole();
    $this->actingAs($candidat)->get(route('notifications.open', $n))->assertRedirect(route('opportunites.show', $o));
    $this->get(route('opportunites.show', $o))->assertSeeText('L’entreprise souhaite échanger');
});

it('laisse retirer sa réponse, efface la pièce jointe et autorise une nouvelle réponse', function () {
    $o = opportunite($this->company);
    $candidat = User::factory()->create();
    $this->actingAs($candidat)->post(route('opportunites.respond.store', $o), ['message' => 'Première.', 'attachment' => pdfOpp()]);
    $r = OpportunityResponse::sole();
    $path = $r->attachment_path;

    $this->actingAs(User::factory()->create())->post(route('opportunites.withdraw', $r))->assertNotFound();
    $this->actingAs($candidat)->post(route('opportunites.withdraw', $r))->assertRedirect();
    expect($r->fresh()->state)->toBe('withdrawn')->and($r->fresh()->message)->toBeNull();
    Storage::disk('local')->assertMissing($path);
    $this->actingAs($this->owner)->get(route('opportunites.responses', $o))->assertDontSeeText('Première.');

    $this->actingAs($candidat)->post(route('opportunites.respond.store', $o), ['message' => 'Seconde.'])->assertRedirect();
    expect(OpportunityResponse::count())->toBe(1)->and($r->fresh()->state)->toBe('sent')->and($r->fresh()->message)->toBe('Seconde.');
});

it('efface les pièces jointes avec l’opportunité', function () {
    $o = opportunite($this->company);
    $candidat = User::factory()->create();
    $this->actingAs($candidat)->post(route('opportunites.respond.store', $o), ['attachment' => pdfOpp()]);
    $path = OpportunityResponse::sole()->attachment_path;
    Storage::disk('local')->assertExists($path);
    $o->delete();
    Storage::disk('local')->assertMissing($path);
    expect(OpportunityResponse::count())->toBe(0);
});

it('efface le CV d’un candidat quand son compte est supprimé, et seulement le sien', function () {
    $o = opportunite($this->company);
    $candidat = User::factory()->create();
    $autre = User::factory()->create();
    $this->actingAs($candidat)->post(route('opportunites.respond.store', $o), ['attachment' => pdfOpp()]);
    $this->actingAs($autre)->post(route('opportunites.respond.store', $o), ['attachment' => pdfOpp()]);
    $sien = OpportunityResponse::where('user_id', $candidat->id)->value('attachment_path');
    $autrui = OpportunityResponse::where('user_id', $autre->id)->value('attachment_path');

    $candidat->delete();

    Storage::disk('local')->assertMissing($sien);
    Storage::disk('local')->assertExists($autrui);
    expect(OpportunityResponse::count())->toBe(1);
});

it('efface les CV reçus quand l’entreprise est supprimée', function () {
    $o = opportunite($this->company);
    $brouillon = opportunite($this->company, ['status' => Opportunity::DRAFT, 'title' => 'Brouillon']);
    $candidat = User::factory()->create();
    $this->actingAs($candidat)->post(route('opportunites.respond.store', $o), ['attachment' => pdfOpp()]);
    $path = OpportunityResponse::sole()->attachment_path;
    $autreEntreprise = entrepriseOpp('Autre Société');
    $autreOffre = opportunite($autreEntreprise);
    $this->actingAs(User::factory()->create())->post(route('opportunites.respond.store', $autreOffre), ['attachment' => pdfOpp()]);
    $autrePath = OpportunityResponse::where('opportunity_id', $autreOffre->id)->value('attachment_path');

    DB::table('user_notifications')->update(['opportunity_id' => null]);
    $this->company->delete();

    Storage::disk('local')->assertMissing($path);
    Storage::disk('local')->assertExists($autrePath);
});

it('efface les CV reçus quand le propriétaire de l’entreprise supprime son compte', function () {
    $o = opportunite($this->company);
    $this->actingAs(User::factory()->create())->post(route('opportunites.respond.store', $o), ['attachment' => pdfOpp()]);
    $path = OpportunityResponse::sole()->attachment_path;

    DB::table('user_notifications')->update(['opportunity_id' => null]);
    $this->owner->delete();

    Storage::disk('local')->assertMissing($path);
});

it('sert le PDF de candidature avec les en-têtes de sécurité des documents privés', function () {
    $o = opportunite($this->company);
    $this->actingAs(User::factory()->create())->post(route('opportunites.respond.store', $o), ['attachment' => pdfOpp()]);
    $response = OpportunityResponse::sole();

    $this->actingAs($this->owner)->get(route('opportunites.attachment', $response))->assertOk()
        ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'attachment; filename="reponse.pdf"');
});

// ─── Purge des fichiers orphelins (gamad:purger-cv-orphelins) ───────────────

it('liste les PDF orphelins sans rien effacer par défaut, et épargne les fichiers référencés ou récents', function () {
    $o = opportunite($this->company);
    $this->actingAs(User::factory()->create())->post(route('opportunites.respond.store', $o), ['attachment' => pdfOpp()]);
    $reference = OpportunityResponse::sole()->attachment_path;
    $disk = Storage::disk('local');
    $disk->put('opportunity-responses/orphelin.pdf', '%PDF-1.4 orphelin');
    $disk->put('opportunity-responses/recent.pdf', '%PDF-1.4 recent');
    touch($disk->path('opportunity-responses/orphelin.pdf'), now()->subDay()->getTimestamp());

    $this->artisan('gamad:purger-cv-orphelins')->expectsOutputToContain('opportunity-responses/orphelin.pdf')->assertSuccessful();

    $disk->assertExists('opportunity-responses/orphelin.pdf');
    $disk->assertExists('opportunity-responses/recent.pdf');
    $disk->assertExists($reference);
});

it('efface seulement les orphelins anciens avec --purger', function () {
    $o = opportunite($this->company);
    $this->actingAs(User::factory()->create())->post(route('opportunites.respond.store', $o), ['attachment' => pdfOpp()]);
    $reference = OpportunityResponse::sole()->attachment_path;
    $disk = Storage::disk('local');
    $disk->put('opportunity-responses/orphelin.pdf', '%PDF-1.4 orphelin');
    $disk->put('opportunity-responses/recent.pdf', '%PDF-1.4 recent');
    touch($disk->path('opportunity-responses/orphelin.pdf'), now()->subDay()->getTimestamp());
    touch($disk->path($reference), now()->subDay()->getTimestamp());

    $this->artisan('gamad:purger-cv-orphelins', ['--purger' => true])->assertSuccessful();

    $disk->assertMissing('opportunity-responses/orphelin.pdf');
    $disk->assertExists('opportunity-responses/recent.pdf');
    $disk->assertExists($reference);
});

it('liste mes réponses avec leur état dans Mon espace', function () {
    $o = opportunite($this->company, ['title' => 'Mon poste suivi']);
    $moi = User::factory()->create();
    $this->actingAs($moi)->post(route('opportunites.respond.store', $o), ['message' => 'Hello']);
    $this->get(route('espace.opportunites'))->assertOk()->assertSeeText('Mon poste suivi')->assertSeeText('Envoyée');
    $this->actingAs($this->owner)->get(route('espace.opportunites'))->assertSeeText('Gérer les opportunités de Orange Test');
});

// ─── Navigation et intégration ──────────────────────────────────────────────

it('ajoute Opportunités au menu global sans retirer les entrées existantes', function () {
    config(['gamad.initial_experience' => true]);
    $html = $this->actingAs(User::factory()->create())->get(route('fil'))->assertOk()->getContent();
    preg_match('/<nav aria-label="Navigation principale" class="hidden.*?<\/nav>/s', $html, $barre);
    expect(trim(preg_replace('/\s+/', ' ', strip_tags($barre[0]))))->toBe('Accueil Personnes Entreprises Opportunités');
});

// ─── Menu hamburger du téléphone (AM-1) ─────────────────────────────────────

function menuMobile(string $html): string
{
    preg_match('/<nav id="menu-mobile".*?<\/nav>/s', $html, $menu);

    return $menu[0] ?? '';
}

it('ouvre sur téléphone un menu Accueil, Personnes, Entreprises, Opportunités, sans toucher à la barre du bas', function () {
    config(['gamad.initial_experience' => true]);
    $html = $this->actingAs(User::factory()->create())->get(route('fil'))->assertOk()->getContent();
    $menu = menuMobile($html);

    expect(trim(preg_replace('/\s+/', ' ', strip_tags($menu))))->toBe('Accueil Personnes Entreprises Opportunités');
    foreach (['fil', 'personnes.index', 'entreprises.index', 'opportunites.index'] as $route) {
        expect($menu)->toContain('href="'.route($route).'"');
    }
    // Accessibilité : un bouton nommé qui annonce son état, relié au menu, fermé après chaque choix et par Échap.
    expect($html)->toContain('aria-controls="menu-mobile"')->toContain('aria-label="Menu"')->toContain('x-bind:aria-expanded="menuOpen.toString()"')
        ->and($menu)->toContain('x-on:click="menuOpen = false"')->and($html)->toContain('x-on:keydown.escape.window');
    // La barre du bas garde ses trois entrées, dans cet ordre.
    preg_match_all('/<nav aria-label="Navigation principale"\s+class="fixed.*?<\/nav>/s', $html, $bas);
    expect(trim(preg_replace('/\s+/', ' ', strip_tags($bas[0][0]))))->toBe('Accueil Agir Mon espace');
});

it('ne rouvre aucun module gelé dans le menu du téléphone et retire l’ancienne icône Opportunités de l’en-tête', function () {
    config(['gamad.initial_experience' => true]);
    $html = $this->actingAs(User::factory()->create())->get(route('fil'))->assertOk()->getContent();
    $menu = menuMobile($html);

    foreach (['ZUMRA', 'Projets', 'Finances', 'Portefeuille', 'Besoins'] as $mot) {
        expect($menu)->not->toContain($mot);
    }
    expect($html)->not->toContain('aria-label="Opportunités"');
});

it('n’affiche le menu du téléphone ni aux visiteurs ni dans l’expérience historique', function () {
    config(['gamad.initial_experience' => true]);
    $this->get(route('entreprises.index'))->assertOk()->assertDontSee('id="menu-mobile"', false);
    config(['gamad.initial_experience' => false]);
    $this->actingAs(User::factory()->create())->get(route('fil'))->assertOk()->assertDontSee('id="menu-mobile"', false);
});

it('retire les opportunités du formulaire de publication ordinaire de l’entreprise : une seule source', function () {
    $this->actingAs($this->owner)->get(route('entreprises.posts.create', $this->company))->assertOk()->assertSee(route('opportunites.create', $this->company), false);
    $this->post(route('entreprises.posts.store', $this->company), ['kind' => 'opportunity', 'body' => 'Offre en double'])->assertSessionHasErrors('kind');
    expect(Publication::where('company_id', $this->company->id)->count())->toBe(0);
});

it('garde la page Entreprise sans opportunité honnête et propose de publier à son propriétaire', function () {
    $this->actingAs(User::factory()->create())->get(route('entreprises.show', $this->company))->assertSeeText('Aucune opportunité en cours pour le moment.')->assertDontSeeText('Publier une opportunité');
    $this->actingAs($this->owner)->get(route('entreprises.show', $this->company))->assertSee(route('opportunites.create', $this->company), false);
});

it('applique la limite de débit aux réponses', function () {
    $o = opportunite($this->company);
    $moi = User::factory()->create();
    $this->actingAs($moi);
    foreach (range(1, 21) as $i) {
        $status = $this->post(route('opportunites.respond.store', $o), ['message' => 'x'])->status();
    }
    expect($status)->toBe(429);
});

it('ignore les accents et la casse dans la recherche : « ingenieur » trouve « Ingénieur »', function () {
    $societe = entrepriseOpp('Société Générale Énergie');
    opportunite($this->company, ['title' => 'Ingénieur réseau', 'description' => 'Déploiement des infrastructures.', 'skills' => ['Électricité']]);
    opportunite($societe, ['title' => 'Comptable', 'description' => 'Tenue des comptes.']);
    $titres = fn ($recherche) => Livewire::test(OpportunityCatalog::class)->set('query', $recherche)->viewData('opportunities')->pluck('title')->all();

    foreach (['ingenieur', 'INGENIEUR', 'Ingénieur', 'ingénieur', 'INGÉNIEUR', '  ingenieur  ', 'reseau', 'deploiement', 'electricite', 'ÉLECTRICITÉ', 'ingenieur reseau', 'reseau ingenieur'] as $saisie) {
        expect($titres($saisie))->toBe(['Ingénieur réseau'], $saisie);
    }
    // Le nom de l'entreprise est aussi cherché sans accents.
    expect($titres('societe generale energie'))->toBe(['Comptable'])->and($titres('SOCIÉTÉ énergie'))->toBe(['Comptable']);
    // Tous les mots doivent figurer : un mot absent exclut l'opportunité.
    expect($titres('ingenieur comptable'))->toBe([])->and($titres('introuvable'))->toBe([]);
    // Les caractères spéciaux de la saisie sont neutralisés.
    expect($titres('100%'))->toBe([])->and($titres('_'))->toBe([]);
});

it('garde la recherche à jour quand l’opportunité ou l’entreprise change', function () {
    $o = opportunite($this->company, ['title' => 'Plombier']);
    expect($o->search_text)->toContain('plombier')->toContain('orange test');
    $o->update(['title' => 'Électricien']);
    expect($o->fresh()->search_text)->toContain('electricien')->not->toContain('plombier');

    $this->company->update(['name' => 'Société Renommée']);
    expect($o->fresh()->search_text)->toContain('societe renommee')->not->toContain('orange test');
    $titres = Livewire::test(OpportunityCatalog::class)->set('query', 'renommee')->viewData('opportunities')->pluck('title')->all();
    expect($titres)->toBe(['Électricien']);
});

it('propose aussi sans accents les pistes selon les capacités du profil', function () {
    opportunite($this->company, ['title' => 'Installateur', 'skills' => ['Électricité']]);
    $moi = person('Awa Capacité', ['electricite']);
    Livewire::actingAs($moi)->test(OpportunityCatalog::class)->assertSeeText('Selon vos capacités')->assertSeeText('Installateur');
});

it('suit le parcours téléphone de la maquette : le type seul à l’étape 1, puis le poste et l’explication à l’étape 2', function () {
    Livewire::actingAs($this->owner)->test(OpportunityForm::class, ['company' => $this->company])
        ->call('nextCompact')->assertHasErrors('kind')->assertSet('step', 1)
        ->call('chooseKind', 'internship')->call('nextCompact')->assertHasNoErrors()->assertSet('step', 2)->assertSet('compact', true)
        ->assertSee('id="title"', false)->assertSee('id="description"', false)->assertSee('1000')
        ->call('preview')->assertHasErrors(['title', 'description'])->assertSet('step', 2)
        ->set('title', 'Stagiaire marketing')->set('description', str_repeat('a', 1001))->call('preview')->assertHasErrors('description')
        ->set('description', 'Participer aux projets digitaux.')->call('preview')->assertHasNoErrors()->assertSet('step', 3)
        ->call('edit')->assertSet('step', 2)->call('preview')->call('publish')->assertRedirect();
    expect(Opportunity::sole()->kind->value)->toBe('internship');
});

it('limite la description à 1 000 caractères, comme le compteur de la maquette', function () {
    Livewire::actingAs($this->owner)->test(OpportunityForm::class, ['company' => $this->company])
        ->call('chooseKind', 'job')->set('title', 'Poste')->set('description', str_repeat('a', 1001))->call('next')->assertHasErrors('description')
        ->set('description', str_repeat('a', 1000))->call('next')->assertHasNoErrors()->assertSet('step', 2);
});

it('affiche le détail avec lien vers l’entreprise, bouton Suivre et chips de type', function () {
    $o = opportunite($this->company, ['title' => 'Poste suivi', 'kind' => 'job']);
    $moi = User::factory()->create();
    $this->actingAs($moi)->get(route('opportunites.show', $o))->assertSeeText('Voir le profil de l’entreprise')->assertSeeText('Suivre')->assertDontSeeText('Ne plus suivre');
    $this->post(route('entreprises.follow', $this->company));
    $this->get(route('opportunites.show', $o))->assertSeeText('Ne plus suivre');
    $this->actingAs($this->owner)->get(route('opportunites.show', $o))->assertDontSeeText('Suivre');
});

it('utilise les libellés courts de la maquette pour les onglets et les compteurs entre parenthèses', function () {
    opportunite($this->company, ['kind' => 'internship']);
    $this->get(route('opportunites.index'))->assertSeeText('Stages')->assertSeeText('Stages et apprentissage')->assertSeeText('(1)')->assertSee('placeholder="Ex. : Abidjan, Yopougon"', false);
});
