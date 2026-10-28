<?php

use App\Enums\NotificationKind;
use App\Enums\PublicationType;
use App\Livewire\JournalAdministration;
use App\Livewire\PageNotifications;
use App\Models\AdminAction;
use App\Models\Company;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AdminJournal;
use App\Services\CompanyRegistry;
use App\Services\Moderation;
use App\Services\PublicationComments;
use App\Services\Reports;
use App\Support\JournalReader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;

beforeEach(function () {
    $this->awa = User::factory()->create(['name' => 'Awa Traoré']);
    $this->yao = User::factory()->create(['name' => 'Yao Kouassi']);
    $this->mod = User::factory()->moderator()->create(['name' => 'Modératrice Une']);
    $this->admin = User::factory()->superAdmin()->create(['name' => 'Président de test']);
});

// ─── Accès ───────────────────────────────────────────────────────────────────

it('réserve le journal au super administrateur et aux modérateurs', function () {
    $this->get(route('moderation.journal'))->assertRedirect(route('login'));
    $this->actingAs($this->yao)->get(route('moderation.journal'))->assertForbidden();
    $this->actingAs($this->mod)->get(route('moderation.journal'))->assertOk()->assertSeeText('Journal d’administration');
    $this->actingAs($this->admin)->get(route('moderation.journal'))->assertOk();
});

// ─── Lecture ─────────────────────────────────────────────────────────────────

it('écrit chaque acte en une phrase : qui, quoi, sur qui, pourquoi, quand', function () {
    AdminJournal::record($this->mod, 'person.suspended', $this->awa, 'Propos répétés.', ['expliquee' => true]);

    Livewire::actingAs($this->mod)->test(JournalAdministration::class)
        ->assertSeeTextInOrder(['Modératrice Une', 'a suspendu', 'Awa Traoré'])
        ->assertSeeText('Motif : Propos répétés.')->assertSeeText('La personne s’était expliquée.');
});

it('nomme une publication par son extrait et son auteur', function () {
    $publication = publication($this->awa, PublicationType::Knowledge, ['body' => 'Atelier de couture ouvert à tous.']);
    app(Reports::class)->submit($this->yao, $publication, 'spam');
    app(Moderation::class)->hide($this->mod, $publication, 'Publicité.');

    Livewire::actingAs($this->mod)->test(JournalAdministration::class)
        ->assertSeeTextInOrder(['Modératrice Une', 'a masqué une publication', 'Atelier de couture ouvert à tous.', 'de Awa Traoré'])->assertSeeText('1 signalement clos');
});

it('nomme un compte supprimé sans planter', function () {
    AdminJournal::record($this->mod, 'person.notice_issued', $this->awa, 'Motif.', ['delai_jours' => 7]);
    fermerCompte($this->awa);

    Livewire::actingAs($this->mod)->test(JournalAdministration::class)->assertSeeText('un compte supprimé')->assertSeeText('Délai pour s’expliquer : 7 jours');
});

it('garde l’acteur lisible après la suppression de son compte', function () {
    AdminJournal::record($this->mod, 'report.dismissed', null, 'Rien à signaler.');
    fermerCompte($this->mod);

    Livewire::actingAs($this->admin)->test(JournalAdministration::class)->assertSeeText('Modératrice Une')->assertSeeText('a classé des signalements sans suite');
});

it('affiche les actes les plus récents d’abord', function () {
    AdminJournal::record($this->mod, 'person.notice_closed', $this->yao);
    $this->travel(2)->days();
    AdminJournal::record($this->mod, 'person.reinstated', $this->awa);

    Livewire::actingAs($this->mod)->test(JournalAdministration::class)->assertSeeTextInOrder(['a rétabli le compte de', 'a classé l’avis de la modération pour']);
});

it('affiche l’état vide', function () {
    Livewire::actingAs($this->mod)->test(JournalAdministration::class)->assertSeeText('Aucun acte à afficher.');
});

it('pagine le journal par 25 actes', function () {
    foreach (range(1, 30) as $i) {
        AdminJournal::record($this->mod, 'person.notice_closed', $this->awa, "Acte numéro $i.");
    }

    Livewire::actingAs($this->mod)->test(JournalAdministration::class)
        ->assertSeeText('Page 1 sur 2')->call('nextPage')->assertSeeText('Page 2 sur 2');
    expect(AdminAction::query()->count())->toBe(30);
});

// ─── Ce que lit chacun ───────────────────────────────────────────────────────

it('ne montre au modérateur que les actes de modération, au super administrateur tout', function () {
    AdminJournal::record($this->mod, 'person.suspended', $this->awa, 'Motif.');
    AdminJournal::record($this->mod, 'report.dismissed', null, 'Sans suite.');
    AdminJournal::record($this->admin, 'setting.changed', payload: ['ancien' => 'a', 'nouveau' => 'b'], subjectType: 'setting', subjectId: 'mail_host');
    AdminJournal::record($this->admin, 'domain.created', payload: ['nom' => 'Santé']);
    AdminJournal::record($this->awa, 'account.renamed_by_owner', $this->awa, payload: ['ancien' => 'Awa', 'nouveau' => 'Awa Traoré']);
    AdminJournal::record($this->admin, 'zumra.recognized');

    $moderateur = Livewire::actingAs($this->mod)->test(JournalAdministration::class)->assertSeeText('a suspendu')->assertSeeText('a classé des signalements sans suite');
    $moderateur->assertDontSeeText('a modifié le réglage')->assertDontSeeText('a ajouté un domaine')->assertDontSeeText('a changé son nom affiché')->assertDontSeeText('a reconnu officiellement');

    Livewire::actingAs($this->admin)->test(JournalAdministration::class)
        ->assertSeeText('a suspendu')->assertSeeText('a modifié le réglage')->assertSeeText('mail_host')->assertSeeText('Avant : a — Après : b')
        ->assertSeeText('a ajouté un domaine')->assertSeeText('a changé son nom affiché')->assertSeeText('a reconnu officiellement la ZUMRA');
});

it('filtre par type d’acte et ignore un filtre hors des droits du lecteur', function () {
    AdminJournal::record($this->mod, 'person.suspended', $this->awa, 'Motif.');
    AdminJournal::record($this->admin, 'setting.changed', payload: ['ancien' => 'a', 'nouveau' => 'b'], subjectType: 'setting', subjectId: 'mail_host');

    Livewire::actingAs($this->admin)->test(JournalAdministration::class)->set('family', 'personnes')->assertSeeText('a suspendu')->assertDontSeeText('a modifié le réglage')
        ->set('family', 'reglages')->assertSeeText('a modifié le réglage')->assertDontSeeText('a suspendu');

    // Un modérateur qui force le filtre « réglages » n'obtient rien de plus, et le filtre retombe sur « Tous ».
    $moderateur = Livewire::actingAs($this->mod)->test(JournalAdministration::class)->set('family', 'reglages');
    $moderateur->assertDontSeeText('a modifié le réglage')->assertSeeText('a suspendu')->assertSet('family', '');
});

it('ne propose au modérateur que les types d’actes de modération', function () {
    Livewire::actingAs($this->mod)->test(JournalAdministration::class)->assertSeeText('Publications')->assertSeeText('Personnes')->assertDontSeeText('Réglages')->assertDontSeeText('Listes de référence');
    Livewire::actingAs($this->admin)->test(JournalAdministration::class)->assertSeeText('Réglages')->assertSeeText('Listes de référence')->assertSeeText('Comptes')->assertSeeText('ZUMRA');
});

it('ne montre jamais une valeur secrète', function () {
    AdminJournal::record($this->admin, 'setting.changed', payload: ['secret' => true, 'etat' => 'modifié'], subjectType: 'setting', subjectId: 'mail_password');

    Livewire::actingAs($this->admin)->test(JournalAdministration::class)->assertSeeText('Valeur secrète : non affichée')->assertDontSeeText('Avant :');
});

it('garde l’action brute lisible quand elle est inconnue', function () {
    expect(JournalReader::label('inconnu.acte'))->toBe('inconnu.acte')->and(JournalReader::label('city.renamed'))->toBe('a renommé une ville');
});

it('reste en lecture seule : le composant n’offre aucun acte de modification', function () {
    $methodes = array_diff(get_class_methods(JournalAdministration::class), get_class_methods(Component::class));

    expect(array_values(array_diff($methodes, ['mount', 'updatingFamily', 'render', 'previousPage', 'nextPage', 'gotoPage', 'resetPage', 'setPage', 'getPage', 'queryStringHandlesPagination'])))->toBe([]);
});

// ─── Notification : l'auteur s'explique ──────────────────────────────────────

it('prévient toute la modération quand l’auteur d’une publication masquée s’explique', function () {
    $publication = publication($this->awa, PublicationType::Knowledge, ['body' => 'Atelier ouvert à tous.']);
    app(Moderation::class)->hide($this->mod, $publication, 'Publicité.');

    app(Moderation::class)->explain($this->awa, $publication->fresh(), 'C’était un atelier gratuit.');
    app(Moderation::class)->explain($this->awa, $publication->fresh(), 'C’était un atelier gratuit, promis.');

    foreach ([$this->mod, $this->admin] as $moderateur) {
        $n = UserNotification::query()->where('user_id', $moderateur->id)->where('kind', NotificationKind::PublicationExplained)->sole();
        expect($n->actor_id)->toBe($this->awa->id)->and($n->publication_id)->toBe($publication->id);
    }
    expect(UserNotification::query()->where('kind', NotificationKind::PublicationExplained)->count())->toBe(2)
        ->and(UserNotification::query()->where('user_id', $this->yao->id)->where('kind', NotificationKind::PublicationExplained)->count())->toBe(0);

    $this->actingAs($this->mod)->get(route('notifications.open', UserNotification::query()->where('user_id', $this->mod->id)->where('kind', NotificationKind::PublicationExplained)->sole()))
        ->assertRedirect(route('moderation', ['onglet' => 'masquees']));

    Livewire::actingAs($this->mod)->test(PageNotifications::class)->assertSeeTextInOrder(['Awa Traoré', 'a écrit son explication sur la publication masquée', 'Atelier ouvert à tous.']);
});

it('ne prévient pas le modérateur auteur de sa propre explication', function () {
    $publication = publication($this->mod, PublicationType::Knowledge, ['body' => 'Atelier du modérateur.']);
    app(Moderation::class)->hide($this->admin, $publication, 'Test.');

    app(Moderation::class)->explain($this->mod, $publication->fresh(), 'Explication.');

    expect(UserNotification::query()->where('user_id', $this->mod->id)->where('kind', NotificationKind::PublicationExplained)->count())->toBe(0)
        ->and(UserNotification::query()->where('user_id', $this->admin->id)->where('kind', NotificationKind::PublicationExplained)->count())->toBe(1);
});

// ─── Entreprises et commentaires (audit A2) ──────────────────────────────────

function entreprisePourJournal(User $owner, string $status = 'pending'): Company
{
    $company = new Company(['name' => 'Atelier Soleil', 'legal_name' => 'Atelier Soleil SARL', 'registration_country' => 'CI', 'registration_number' => 'RCCM-'.Str::random(10),
        'sector' => 'Artisanat', 'city' => 'Abidjan', 'summary' => 'Une entreprise de test.']);
    $company->slug = 'atelier-soleil-'.strtolower(Str::random(6));
    $company->forceFill(['owner_id' => $owner->id, 'status' => $status, 'verified_at' => $status === 'verified' ? now() : null])->save();

    return $company;
}

it('montre dans le journal la vérification, le refus et la suspension d’une entreprise, avec son nom et le motif', function () {
    $registry = app(CompanyRegistry::class);
    $verified = entreprisePourJournal($this->awa);
    $registry->review($this->admin, $verified, $verified->fresh()->revision, 'verify');
    $registry->review($this->admin, $verified->fresh(), $verified->fresh()->revision, 'suspend', 'Document non conforme.');
    $declined = entreprisePourJournal($this->yao);
    $registry->review($this->admin, $declined, $declined->fresh()->revision, 'decline', 'Numéro introuvable.');

    Livewire::actingAs($this->admin)->test(JournalAdministration::class)
        ->assertSeeText('a vérifié l’entreprise')->assertSeeText('a suspendu l’entreprise')->assertSeeText('a refusé la vérification de l’entreprise')
        ->assertSeeText('Atelier Soleil')->assertSeeText('Motif : Document non conforme.')->assertSeeText('Motif : Numéro introuvable.');

    Livewire::actingAs($this->admin)->test(JournalAdministration::class)->set('family', 'entreprises')->assertSeeText('a suspendu l’entreprise')->assertDontSeeText('a masqué');
});

it('montre dans le journal les actes de modération des commentaires, y compris à un modérateur', function () {
    $publication = publication($this->awa, PublicationType::Share, ['body' => 'Une expérience de couture.']);
    $comment = app(PublicationComments::class)->create($this->yao, $publication, 'Un commentaire à masquer.');
    app(PublicationComments::class)->hide($this->mod, $comment, 'Propos déplacés.');

    Livewire::actingAs($this->mod)->test(JournalAdministration::class)
        ->assertSeeTextInOrder(['Modératrice Une', 'a masqué un commentaire', 'Un commentaire à masquer.', 'de Yao Kouassi'])->assertSeeText('Motif : Propos déplacés.');
    Livewire::actingAs($this->mod)->test(JournalAdministration::class)->set('family', 'commentaires')->assertSeeText('a masqué un commentaire');
});

it('ne montre pas au modérateur les actes sur les entreprises', function () {
    $company = entreprisePourJournal($this->awa);
    app(CompanyRegistry::class)->review($this->admin, $company, $company->fresh()->revision, 'verify');

    expect(JournalReader::familiesFor($this->mod))->not->toContain('entreprises')
        ->and(JournalReader::query($this->mod)->count())->toBe(0)
        ->and(JournalReader::query($this->mod, 'entreprises')->count())->toBe(0)
        ->and(JournalReader::query($this->admin)->count())->toBe(1);
});

it('lit tout acte que le code écrit : chaque action du code appartient à une famille et a un libellé', function () {
    $actions = collect(File::allFiles(app_path()))
        ->flatMap(function ($file) {
            preg_match_all("/AdminJournal::record\(\s*[^,]+,\s*'([a-z_.]+)'\s*[,)]/", $file->getContents(), $m);

            return $m[1];
        })->unique()->values();

    expect($actions)->not->toBeEmpty();
    $prefixes = collect(JournalReader::FAMILIES)->flatMap(fn ($family) => $family[1]);
    foreach ($actions as $action) {
        expect($prefixes->contains(fn ($prefix) => str_starts_with($action, $prefix)))->toBeTrue("« $action » n’appartient à aucune famille du journal.");
        expect(JournalReader::label($action))->not->toBe($action, "« $action » n’a pas de libellé lisible.");
    }
});
