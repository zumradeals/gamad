<?php

use App\Enums\NotificationKind;
use App\Livewire\AvisModeration;
use App\Livewire\CarrefourPersonnes;
use App\Livewire\ModerationPersonnes;
use App\Livewire\PageNotifications;
use App\Models\AdminAction;
use App\Models\ModerationNotice;
use App\Models\Profile;
use App\Models\Publication;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\LegalTexts;
use App\Services\PersonModeration;
use App\Services\Todo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->awa = User::factory()->create(['name' => 'Awa Traoré']);
    $this->yao = User::factory()->create(['name' => 'Yao Kouassi']);
    $this->mod = User::factory()->moderator()->create(['name' => 'Modératrice Une']);
    $this->mod2 = User::factory()->moderator()->create(['name' => 'Modérateur Deux']);
    $this->admin = User::factory()->superAdmin()->create(['name' => 'Président de test']);
});

function personnes(): PersonModeration
{
    return app(PersonModeration::class);
}

function avis(User $par, User $personne, string $motif = 'Propos déplacés envers d’autres membres.'): ModerationNotice
{
    return personnes()->issueNotice($par, $personne, $motif);
}

/** Une personne suspendue en bonne et due forme : avis, explication, suspension. */
function suspendre(User $par, User $personne, string $motif = 'Propos répétés après avertissement.'): void
{
    $notice = avis($par, $personne);
    personnes()->explain($personne, 'Je regrette.');
    personnes()->suspend($par, $notice->fresh(), $motif);
}

// ─── Avis ────────────────────────────────────────────────────────────────────

it('envoie un avis avec le motif, l’inscrit au journal et prévient la personne', function () {
    $notice = avis($this->mod, $this->awa);

    expect($notice->user_id)->toBe($this->awa->id)->and($notice->issued_by)->toBe($this->mod->id)->and($notice->reason)->toBe('Propos déplacés envers d’autres membres.')
        ->and($notice->isOpen())->toBeTrue()->and($notice->isExplained())->toBeFalse();

    $a = AdminAction::query()->where('action', 'person.notice_issued')->sole();
    expect($a->actor_id)->toBe($this->mod->id)->and($a->subject_id)->toBe($this->awa->id)->and($a->subject_type)->toBe($this->awa->getMorphClass())
        ->and($a->reason)->toBe('Propos déplacés envers d’autres membres.')->and($a->payload['notice_id'])->toBe($notice->id)->and($a->payload['delai_jours'])->toBe(7);

    $n = UserNotification::query()->where('user_id', $this->awa->id)->sole();
    expect($n->kind)->toBe(NotificationKind::ModerationNoticeIssued)->and($n->actor_id)->toBe($this->mod->id);
});

it('exige un motif d’au plus 500 caractères et n’écrit rien sans motif', function () {
    foreach ([null, '', '   '] as $vide) {
        expect(fn () => personnes()->issueNotice($this->mod, $this->awa, $vide))->toThrow(ValidationException::class, 'Indiquez le motif de l’avis');
    }

    expect(fn () => personnes()->issueNotice($this->mod, $this->awa, str_repeat('a', 501)))->toThrow(ValidationException::class, '500 caractères');
    personnes()->issueNotice($this->mod, $this->awa, str_repeat('a', 500));

    expect(ModerationNotice::query()->count())->toBe(1)->and(AdminAction::query()->count())->toBe(1);
});

it('refuse l’avis à une personne ordinaire et à un visiteur de la modération, même en appelant le service', function () {
    expect(fn () => personnes()->issueNotice($this->yao, $this->awa, 'Motif'))->toThrow(AuthorizationException::class);

    expect(ModerationNotice::query()->count())->toBe(0)->and(AdminAction::query()->count())->toBe(0);
});

it('ne laisse sanctionner ni soi-même, ni le super administrateur ; un modérateur ne sanctionne pas un modérateur', function () {
    foreach ([[$this->mod, $this->mod], [$this->admin, $this->admin], [$this->mod, $this->admin], [$this->mod2, $this->admin], [$this->mod, $this->mod2]] as [$acteur, $cible]) {
        expect(fn () => personnes()->issueNotice($acteur, $cible, 'Motif'))->toThrow(AuthorizationException::class);
    }

    // Le super administrateur, lui, peut envoyer un avis à un modérateur.
    avis($this->admin, $this->mod2);

    expect(ModerationNotice::query()->count())->toBe(1);
    expect(Gate::forUser($this->mod)->allows('sanction-person', $this->awa))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('sanction-person', $this->admin))->toBeFalse();
});

it('refuse un second avis tant que le premier est ouvert, et un avis à une personne déjà suspendue', function () {
    avis($this->mod, $this->awa);

    expect(fn () => avis($this->mod2, $this->awa))->toThrow(ValidationException::class, 'Un avis est déjà en cours');

    suspendre($this->mod, $this->yao);
    expect(fn () => avis($this->mod, $this->yao->fresh()))->toThrow(ValidationException::class, 'déjà suspendue');
    expect(ModerationNotice::query()->count())->toBe(2);
});

// ─── Explication de la personne ──────────────────────────────────────────────

it('enregistre l’explication de la personne et prévient toute la modération, pas deux fois', function () {
    avis($this->mod, $this->awa);

    personnes()->explain($this->awa, '  Je n’avais pas compris la règle.  ');
    personnes()->explain($this->awa, 'Je n’avais pas compris la règle, pardon.');

    $notice = $this->awa->openNotice();
    expect($notice->explanation)->toBe('Je n’avais pas compris la règle, pardon.')->and($notice->isExplained())->toBeTrue();

    foreach ([$this->mod, $this->mod2, $this->admin] as $moderateur) {
        $n = UserNotification::query()->where('user_id', $moderateur->id)->where('kind', NotificationKind::ModerationNoticeExplained)->sole();
        expect($n->actor_id)->toBe($this->awa->id);
    }

    // Une personne ordinaire n'est pas prévenue.
    expect(UserNotification::query()->where('user_id', $this->yao->id)->count())->toBe(0);
});

it('prévient de nouveau la modération quand la première notification a été lue', function () {
    avis($this->mod, $this->awa);
    personnes()->explain($this->awa, 'Première version.');
    UserNotification::query()->where('kind', NotificationKind::ModerationNoticeExplained)->update(['read_at' => now()]);

    personnes()->explain($this->awa, 'Seconde version.');

    expect(UserNotification::query()->where('kind', NotificationKind::ModerationNoticeExplained)->count())->toBe(6)
        ->and(UserNotification::query()->where('kind', NotificationKind::ModerationNoticeExplained)->whereNull('read_at')->count())->toBe(3);
});

it('refuse une explication vide, trop longue, ou sans avis ouvert', function () {
    expect(fn () => personnes()->explain($this->awa, 'Merci'))->toThrow(ValidationException::class, 'Aucun avis');

    avis($this->mod, $this->awa);

    foreach ([null, '', '   '] as $vide) {
        expect(fn () => personnes()->explain($this->awa, $vide))->toThrow(ValidationException::class, 'Écrivez votre explication');
    }
    expect(fn () => personnes()->explain($this->awa, str_repeat('a', 1001)))->toThrow(ValidationException::class, '1000 caractères');
    personnes()->explain($this->awa, str_repeat('a', 1000));

    expect($this->awa->openNotice()->isExplained())->toBeTrue();
});

it('ne laisse pas une autre personne répondre à l’avis d’autrui', function () {
    avis($this->mod, $this->awa);

    expect(fn () => personnes()->explain($this->yao, 'Je réponds à la place d’Awa.'))->toThrow(ValidationException::class, 'Aucun avis');
    expect($this->awa->openNotice()->isExplained())->toBeFalse();
});

// ─── Suspendre ───────────────────────────────────────────────────────────────

it('refuse de suspendre avant l’explication ou la fin du délai, puis l’autorise dès l’explication', function () {
    $notice = avis($this->mod, $this->awa);

    expect(fn () => personnes()->suspend($this->mod, $notice, 'Motif'))->toThrow(ValidationException::class, 'a jusqu’au');
    expect($this->awa->fresh()->isSuspended())->toBeFalse()->and(AdminAction::query()->where('action', 'person.suspended')->count())->toBe(0);

    $this->travel(6)->days();
    expect(fn () => personnes()->suspend($this->mod, $notice, 'Motif'))->toThrow(ValidationException::class, 'a jusqu’au');

    personnes()->explain($this->awa, 'Voici mon explication.');
    personnes()->suspend($this->mod, $notice->fresh(), 'Motif de la suspension.');

    expect($this->awa->fresh()->isSuspended())->toBeTrue();
});

it('autorise la suspension une fois le délai écoulé sans réponse', function () {
    $notice = avis($this->mod, $this->awa);

    $this->travel(7 * 24 + 1)->hours();
    personnes()->suspend($this->mod, $notice, 'Aucune réponse après l’avis.');

    expect($this->awa->fresh()->isSuspended())->toBeTrue();
    expect(AdminAction::query()->where('action', 'person.suspended')->sole()->payload['expliquee'])->toBeFalse();
});

it('suspend avec un motif que la personne verra, clôt l’avis et l’inscrit au journal', function () {
    $notice = avis($this->mod, $this->awa);
    personnes()->explain($this->awa, 'Explication.');

    personnes()->suspend($this->mod2, $notice->fresh(), 'Propos répétés après avertissement.');

    $awa = $this->awa->fresh();
    expect($awa->isSuspended())->toBeTrue()->and($awa->suspension_reason)->toBe('Propos répétés après avertissement.')->and($awa->suspended_by)->toBe($this->mod2->id)
        ->and($awa->openNotice())->toBeNull();

    $notice = $notice->fresh();
    expect($notice->resolution)->toBe('suspended')->and($notice->resolved_by)->toBe($this->mod2->id)->and($notice->isOpen())->toBeFalse();

    $a = AdminAction::query()->where('action', 'person.suspended')->sole();
    expect($a->actor_id)->toBe($this->mod2->id)->and($a->subject_id)->toBe($this->awa->id)->and($a->reason)->toBe('Propos répétés après avertissement.')
        ->and($a->payload['notice_id'])->toBe($notice->id)->and($a->payload['expliquee'])->toBeTrue();
});

it('exige un motif de suspension d’au plus 500 caractères', function () {
    $notice = avis($this->mod, $this->awa);
    personnes()->explain($this->awa, 'Explication.');

    foreach ([null, '', '  '] as $vide) {
        expect(fn () => personnes()->suspend($this->mod, $notice->fresh(), $vide))->toThrow(ValidationException::class, 'Indiquez le motif de la suspension');
    }
    expect(fn () => personnes()->suspend($this->mod, $notice->fresh(), str_repeat('a', 501)))->toThrow(ValidationException::class, '500 caractères');

    expect($this->awa->fresh()->isSuspended())->toBeFalse();
});

it('refuse de suspendre sans droit, sur un avis clos, ou une personne que l’acteur n’a pas le droit de sanctionner', function () {
    $notice = avis($this->mod, $this->awa);
    personnes()->explain($this->awa, 'Explication.');

    expect(fn () => personnes()->suspend($this->yao, $notice->fresh(), 'Motif'))->toThrow(AuthorizationException::class);

    personnes()->closeNotice($this->mod, $notice->fresh());
    expect(fn () => personnes()->suspend($this->mod, $notice->fresh(), 'Motif'))->toThrow(ValidationException::class, 'déjà clos');

    // Un modérateur ne suspend pas un modérateur, même avec un avis ouvert par le super administrateur.
    $avisModerateur = avis($this->admin, $this->mod2);
    personnes()->explain($this->mod2, 'Explication.');
    expect(fn () => personnes()->suspend($this->mod, $avisModerateur->fresh(), 'Motif'))->toThrow(AuthorizationException::class);
    personnes()->suspend($this->admin, $avisModerateur->fresh(), 'Décision du Président.');

    expect($this->mod2->fresh()->isSuspended())->toBeTrue()->and($this->awa->fresh()->isSuspended())->toBeFalse();
});

// ─── Classer l'avis, rétablir ────────────────────────────────────────────────

it('classe l’avis sans suite : la personne est prévenue, rien ne change pour elle, et un nouvel avis redevient possible', function () {
    $notice = avis($this->mod, $this->awa);

    personnes()->closeNotice($this->mod2, $notice, 'Malentendu levé.');

    expect($this->awa->fresh()->isSuspended())->toBeFalse()->and($notice->fresh()->resolution)->toBe('closed')->and($notice->fresh()->resolved_by)->toBe($this->mod2->id);

    $a = AdminAction::query()->where('action', 'person.notice_closed')->sole();
    expect($a->actor_id)->toBe($this->mod2->id)->and($a->subject_id)->toBe($this->awa->id)->and($a->reason)->toBe('Malentendu levé.')->and($a->payload['expliquee'])->toBeFalse();
    expect(UserNotification::query()->where('user_id', $this->awa->id)->where('kind', NotificationKind::ModerationNoticeClosed)->sole()->actor_id)->toBe($this->mod2->id);

    expect(fn () => personnes()->closeNotice($this->mod, $notice->fresh()))->toThrow(ValidationException::class, 'déjà clos');
    avis($this->mod, $this->awa);
    expect(ModerationNotice::query()->count())->toBe(2);
});

it('refuse de classer un avis sans droit', function () {
    $notice = avis($this->mod, $this->awa);

    expect(fn () => personnes()->closeNotice($this->yao, $notice))->toThrow(AuthorizationException::class);
    expect(fn () => personnes()->closeNotice($this->mod, avis($this->admin, $this->mod2)))->toThrow(AuthorizationException::class);
    expect($notice->fresh()->isOpen())->toBeTrue();
});

it('rétablit une personne suspendue, prévient la personne et l’inscrit au journal', function () {
    suspendre($this->mod, $this->awa);

    personnes()->reinstate($this->mod2, $this->awa->fresh(), '  Réexamen favorable.  ');

    $awa = $this->awa->fresh();
    expect($awa->isSuspended())->toBeFalse()->and($awa->suspension_reason)->toBeNull()->and($awa->suspended_by)->toBeNull();

    $a = AdminAction::query()->where('action', 'person.reinstated')->sole();
    expect($a->actor_id)->toBe($this->mod2->id)->and($a->subject_id)->toBe($this->awa->id)->and($a->reason)->toBe('Réexamen favorable.');
    expect(UserNotification::query()->where('user_id', $this->awa->id)->where('kind', NotificationKind::PersonReinstated)->sole()->actor_id)->toBe($this->mod2->id);
});

it('rétablit sans raison, tracé sans raison, mais refuse une raison trop longue et une personne non suspendue', function () {
    suspendre($this->mod, $this->awa);

    expect(fn () => personnes()->reinstate($this->mod, $this->awa->fresh(), str_repeat('a', 501)))->toThrow(ValidationException::class, '500 caractères');
    expect(fn () => personnes()->reinstate($this->mod, $this->yao))->toThrow(ValidationException::class, 'pas suspendue');

    personnes()->reinstate($this->mod, $this->awa->fresh());
    expect(AdminAction::query()->where('action', 'person.reinstated')->sole()->reason)->toBeNull();
});

it('réserve le rétablissement à la modération, et celui d’un modérateur au super administrateur', function () {
    suspendre($this->mod, $this->awa);
    suspendre($this->admin, $this->mod2);

    expect(fn () => personnes()->reinstate($this->yao, $this->awa->fresh()))->toThrow(AuthorizationException::class);
    expect(fn () => personnes()->reinstate($this->mod, $this->mod2->fresh()))->toThrow(AuthorizationException::class);
    expect($this->awa->fresh()->isSuspended())->toBeTrue()->and($this->mod2->fresh()->isSuspended())->toBeTrue();

    personnes()->reinstate($this->admin, $this->mod2->fresh());
    expect($this->mod2->fresh()->isSuspended())->toBeFalse();
});

// ─── Effets de la suspension ─────────────────────────────────────────────────

it('ramène toute page d’une personne suspendue à l’écran qui lui dit pourquoi', function () {
    suspendre($this->mod, $this->awa, 'Propos répétés après avertissement.');
    $awa = $this->awa->fresh();

    foreach (['fil', 'espace', 'personnes.index', 'zumras.index', 'projets.index', 'notifications', 'agir', 'avis'] as $route) {
        $this->actingAs($awa)->get(route($route))->assertRedirect(route('suspendu'));
    }

    $this->actingAs($awa)->get('/')->assertRedirect(route('suspendu'));
    $this->actingAs($awa)->get(route('suspendu'))->assertOk()->assertSeeText('Votre compte est suspendu')->assertSeeText('Propos répétés après avertissement.');
});

it('refuse à une requête Livewire d’une personne suspendue, même ouverte avant la suspension', function () {
    suspendre($this->mod, $this->awa);

    $this->actingAs($this->awa->fresh())->withHeader('X-Livewire', 'true')->postJson('/livewire/update', ['components' => []])->assertForbidden();
});

it('laisse la personne suspendue se déconnecter et lire la charte adoptée', function () {
    app(LegalTexts::class)->adoptCharter($this->admin);
    suspendre($this->mod, $this->awa);
    $awa = $this->awa->fresh();

    $this->actingAs($awa)->get(route('charte'))->assertOk();
    $this->actingAs($awa)->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

it('ne laisse à une personne suspendue aucune porte par une action POST', function () {
    suspendre($this->mod, $this->awa);

    $this->actingAs($this->awa->fresh())->post(route('verification.send'))->assertRedirect(route('suspendu'));
});

it('n’affiche l’écran de suspension qu’aux personnes suspendues', function () {
    $this->get(route('suspendu'))->assertRedirect(route('login'));
    $this->actingAs($this->yao)->get(route('suspendu'))->assertRedirect(route('fil'));
});

it('n’y indique un contact que si les mentions légales sont adoptées, jamais un contact inventé', function () {
    suspendre($this->mod, $this->awa);
    $awa = $this->awa->fresh();

    $this->actingAs($awa)->get(route('suspendu'))->assertDontSeeText('réexamen');

    app(LegalTexts::class)->saveLegalValues($this->admin, ['legal_publisher' => 'Association GAMAD', 'legal_director' => 'Koné Djakaridja', 'legal_contact' => 'contact@gamad.test', 'legal_host' => 'Contabo']);
    $this->actingAs($awa)->get(route('suspendu'))->assertDontSeeText('contact@gamad.test');

    app(LegalTexts::class)->adoptLegal($this->admin);
    $this->actingAs($awa)->get(route('suspendu'))->assertSeeText('Pour demander un réexamen')->assertSeeText('contact@gamad.test');
});

it('retire la personne suspendue de ce que voient les autres, et la rend dès le rétablissement', function () {
    $publication = publication($this->awa, data: ['body' => 'Atelier de couture ouvert à tous.']);
    $profil = $this->awa->ensureProfile();
    $profil->forceFill(['visibility' => 'public'])->save();

    $this->get(route('personnes.show', $profil->public_id))->assertOk();
    $this->get(route('publications.show', $publication))->assertOk();
    expect(Profile::query()->visibleTo($this->yao)->where('user_id', $this->awa->id)->exists())->toBeTrue()
        ->and(Publication::query()->visibleTo($this->yao)->whereKey($publication->id)->exists())->toBeTrue()
        ->and(User::query()->active()->count())->toBe(5);

    suspendre($this->mod, $this->awa);

    $this->get(route('personnes.show', $profil->public_id))->assertNotFound();
    $this->actingAs($this->yao)->get(route('personnes.show', $profil->public_id))->assertNotFound();
    $this->get(route('publications.show', $publication))->assertNotFound();
    $this->actingAs($this->yao)->get(route('publications.show', $publication))->assertNotFound();
    expect(Profile::query()->visibleTo($this->yao)->where('user_id', $this->awa->id)->exists())->toBeFalse()
        ->and(Publication::query()->visibleTo($this->yao)->whereKey($publication->id)->exists())->toBeFalse()
        ->and(User::query()->active()->count())->toBe(4);

    personnes()->reinstate($this->mod, $this->awa->fresh());

    $this->get(route('personnes.show', $profil->public_id))->assertOk();
    $this->get(route('publications.show', $publication))->assertOk();
});

it('ne compte plus la personne suspendue parmi les membres d’une ZUMRA ni à l’accueil', function () {
    $zumra = zumra($this->yao);
    ajouteMembre($zumra, $this->awa);
    $this->awa->forceFill(['email_verified_at' => now()])->save();

    monde($this->yao, $zumra)->assertSeeText('Awa Traoré');

    suspendre($this->mod, $this->awa);

    monde($this->yao, $zumra)->assertDontSeeText('Awa Traoré');
    Livewire::actingAs($this->yao)->test(CarrefourPersonnes::class)->assertDontSeeText('Awa Traoré');
});

// ─── Écran de l'avis (la personne) ───────────────────────────────────────────

it('montre à la personne son avis, le motif et le délai, et enregistre son explication', function () {
    avis($this->mod, $this->awa, 'Propos déplacés envers d’autres membres.');

    Livewire::actingAs($this->awa)->test(AvisModeration::class)
        ->assertSeeText('Propos déplacés envers d’autres membres.')->assertSeeText('Modératrice Une')->assertSeeText('Envoyer mon explication')
        ->set('explanation', 'Je comprends et je m’excuse.')->call('explain')->assertHasNoErrors()
        ->assertSeeText('Votre explication est envoyée à la modération.')->assertSeeText('Mettre à jour mon explication');

    expect($this->awa->openNotice()->explanation)->toBe('Je comprends et je m’excuse.');
});

it('affiche les erreurs d’explication et l’état vide', function () {
    Livewire::actingAs($this->awa)->test(AvisModeration::class)->assertSeeText('Aucun avis de la modération ne vous attend.')
        ->set('explanation', 'Texte')->call('explain')->assertHasErrors('explanation');

    avis($this->mod, $this->awa);
    Livewire::actingAs($this->awa)->test(AvisModeration::class)->set('explanation', '')->call('explain')->assertHasErrors(['explanation' => 'Écrivez votre explication.']);
});

it('ne montre l’avis qu’à la personne visée', function () {
    avis($this->mod, $this->awa, 'Motif réservé à Awa.');

    Livewire::actingAs($this->yao)->test(AvisModeration::class)->assertDontSeeText('Motif réservé à Awa.')->assertSeeText('Aucun avis');
});

it('ajoute l’avis à « À faire maintenant » tant que la personne ne s’est pas expliquée', function () {
    expect(app(Todo::class)->for($this->awa))->toBe([]);

    avis($this->mod, $this->awa);
    $lignes = app(Todo::class)->for($this->awa);
    expect($lignes)->toHaveCount(1)->and($lignes[0]['title'])->toBe('Un avis de la modération vous attend')->and($lignes[0]['url'])->toBe(route('avis'));

    personnes()->explain($this->awa, 'Explication.');
    expect(app(Todo::class)->for($this->awa))->toBe([]);
});

// ─── Notifications ───────────────────────────────────────────────────────────

it('mène chaque notification de modération à son écran', function () {
    avis($this->mod, $this->awa);
    personnes()->explain($this->awa, 'Explication.');
    $notice = $this->awa->openNotice();
    personnes()->closeNotice($this->mod, $notice);
    suspendre($this->mod, $this->yao);
    personnes()->reinstate($this->mod, $this->yao->fresh());

    $vers = fn (User $qui, NotificationKind $kind) => $this->actingAs($qui)->get(route('notifications.open', UserNotification::query()->where('user_id', $qui->id)->where('kind', $kind)->firstOrFail()));

    $vers($this->awa, NotificationKind::ModerationNoticeIssued)->assertRedirect(route('avis'));
    $vers($this->awa, NotificationKind::ModerationNoticeClosed)->assertRedirect(route('espace'));
    $vers($this->mod, NotificationKind::ModerationNoticeExplained)->assertRedirect(route('moderation.personnes'));
    $vers($this->yao, NotificationKind::PersonReinstated)->assertRedirect(route('fil'));
});

it('écrit les phrases des notifications de modération', function () {
    avis($this->mod, $this->awa);
    personnes()->explain($this->awa, 'Explication.');

    Livewire::actingAs($this->awa)->test(PageNotifications::class)->assertSeeTextInOrder(['Modératrice Une', 'vous a envoyé un avis de la modération. Vous pouvez vous expliquer.']);
    Livewire::actingAs($this->mod2)->test(PageNotifications::class)->assertSeeTextInOrder(['Awa Traoré', 'a répondu à l’avis de la modération.']);
});

// ─── Écran de la modération ──────────────────────────────────────────────────

it('réserve l’écran des personnes à la modération, et chaque acte du composant', function () {
    $this->get(route('moderation.personnes'))->assertRedirect(route('login'));
    $this->actingAs($this->yao)->get(route('moderation.personnes'))->assertForbidden();
    $this->actingAs($this->mod)->get(route('moderation.personnes'))->assertOk()->assertSeeText('Modération des personnes');
    $this->actingAs($this->admin)->get(route('moderation.personnes'))->assertOk();

    $notice = avis($this->mod, $this->awa);
    $this->actingAs($this->yao);
    $composant = new ModerationPersonnes;

    foreach ([['issue', $this->awa->id], ['suspend', $notice->id], ['closeNotice', $notice->id], ['reinstate', $this->awa->id]] as [$acte, $id]) {
        expect(fn () => $composant->{$acte}($id, personnes()))->toThrow(AuthorizationException::class);
    }

    expect($this->awa->fresh()->isSuspended())->toBeFalse()->and($notice->fresh()->isOpen())->toBeTrue();
});

it('cherche une personne par son nom et lui envoie un avis depuis l’écran', function () {
    Livewire::actingAs($this->mod)->test(ModerationPersonnes::class)
        ->set('search', 'a')->assertDontSeeText('Awa Traoré')
        ->set('search', 'awa tra')->assertSeeText('Awa Traoré')->assertDontSeeText('Yao Kouassi')
        ->call('issue', $this->awa->id)->assertHasErrors('reasons.'.$this->awa->id)
        ->set('reasons.'.$this->awa->id, 'Propos déplacés.')->call('issue', $this->awa->id)
        ->assertHasNoErrors()->assertSeeText('Avis envoyé. La personne a 7 jours pour s’expliquer.')->assertSeeText('Avis en cours (1)');

    expect($this->awa->openNotice()->reason)->toBe('Propos déplacés.');
});

it('ne propose ni soi-même, ni le super administrateur, ni un modérateur à un modérateur', function () {
    $ecran = Livewire::actingAs($this->mod)->test(ModerationPersonnes::class)->set('search', 'mod');
    $ecran->assertDontSeeText('Modératrice Une')->assertDontSeeText('Modérateur Deux');

    Livewire::actingAs($this->mod)->test(ModerationPersonnes::class)->set('search', 'président')->assertDontSeeText('Président de test')->assertSeeText('Aucune personne ne correspond');
    Livewire::actingAs($this->admin)->test(ModerationPersonnes::class)->set('search', 'mod')->assertSeeText('Modérateur Deux')->assertSeeText('Modératrice Une');
});

it('indique qu’un avis est déjà en cours dans les résultats de recherche', function () {
    avis($this->mod, $this->awa);

    Livewire::actingAs($this->mod)->test(ModerationPersonnes::class)->set('search', 'awa')->assertSeeText('Un avis est déjà en cours pour cette personne.')->assertDontSeeText('Envoyer l’avis');
});

it('ne propose « Suspendre » qu’une fois l’explication reçue ou le délai écoulé', function () {
    $notice = avis($this->mod, $this->awa);

    Livewire::actingAs($this->mod)->test(ModerationPersonnes::class)
        ->assertSeeText('Pas encore d’explication. Elle a jusqu’au')->assertDontSeeText('Suspendre la personne')->assertSeeText('Classer l’avis sans suite')
        ->call('suspend', $notice->id)->assertHasErrors('reasons.'.$notice->id);

    personnes()->explain($this->awa, 'Voici pourquoi.');

    Livewire::actingAs($this->mod)->test(ModerationPersonnes::class)->assertSeeText('Voici pourquoi.')->assertSeeText('Suspendre la personne')
        ->set('reasons.'.$notice->id, 'Récidive.')->call('suspend', $notice->id)
        ->assertHasNoErrors()->assertSeeText('Personne suspendue.')->assertSeeText('Personnes suspendues (1)')->assertSeeText('Récidive.');

    $this->travel(8)->days();
    $autre = avis($this->mod, $this->yao);
    $this->travel(8)->days();
    Livewire::actingAs($this->mod)->test(ModerationPersonnes::class)->assertSeeText('le délai est écoulé')->assertSeeText('Suspendre la personne');
});

it('classe un avis et rétablit une personne depuis l’écran', function () {
    $notice = avis($this->mod, $this->awa);
    suspendre($this->mod, $this->yao);

    Livewire::actingAs($this->mod)->test(ModerationPersonnes::class)
        ->call('closeNotice', $notice->id)->assertHasNoErrors()->assertSeeText('Avis classé sans suite')->assertSeeText('Aucun avis en cours.')
        ->set('reasons.'.$this->yao->id, 'Réexamen.')->call('reinstate', $this->yao->id)->assertHasNoErrors()->assertSeeText('Compte rétabli')->assertSeeText('Aucune personne suspendue.');

    expect($this->yao->fresh()->isSuspended())->toBeFalse();
});

it('ne laisse à un modérateur aucun acte sur un modérateur dans l’écran', function () {
    $avisModerateur = avis($this->admin, $this->mod2);

    Livewire::actingAs($this->mod)->test(ModerationPersonnes::class)->assertSeeText('Seul le super administrateur décide pour cette personne.')->assertDontSeeText('Classer l’avis sans suite');
    Livewire::actingAs($this->mod)->test(ModerationPersonnes::class)->call('closeNotice', $avisModerateur->id)->assertForbidden();

    expect($avisModerateur->fresh()->isOpen())->toBeTrue();
});

it('refuse les identifiants invalides de l’écran', function () {
    Livewire::actingAs($this->mod)->test(ModerationPersonnes::class)->call('issue', 'pas-un-uuid')->assertNotFound();
    Livewire::actingAs($this->mod)->test(ModerationPersonnes::class)->call('suspend', 'pas-un-uuid')->assertNotFound();
});

// ─── Garanties de la base ────────────────────────────────────────────────────

it('garantit en base un seul avis ouvert par personne', function () {
    avis($this->mod, $this->awa);

    expect(fn () => ModerationNotice::query()->forceCreate(['user_id' => $this->awa->id, 'reason' => 'Second']))->toThrow(QueryException::class);
});

it('garantit en base qu’une suspension a toujours un motif', function () {
    expect(fn () => $this->awa->forceFill(['suspended_at' => now()])->save())->toThrow(QueryException::class);
});

it('efface l’avis avec le compte mais garde la trace au journal', function () {
    avis($this->mod, $this->awa);

    $this->awa->delete();

    expect(ModerationNotice::query()->count())->toBe(0)->and(AdminAction::query()->where('action', 'person.notice_issued')->count())->toBe(1);
});
