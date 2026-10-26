<?php

use App\Enums\JoinRequestState;
use App\Enums\NotificationKind;
use App\Enums\ProjectState;
use App\Enums\ZumraRole;
use App\Livewire\MesProjets;
use App\Livewire\MondeProjet;
use App\Livewire\PageNotifications;
use App\Livewire\ProjetVitrine;
use App\Models\ParticipationRequest;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Memberships;
use App\Services\ParticipationRequests;
use App\Services\ProjectInvitations;
use App\Services\Projects;
use App\Services\ProjectShowcase;
use App\Services\ProjectTeam;
use App\Services\Todo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** L'équipe d'un projet (P1, D-206) : des collaborateurs qui ont accepté, invités par le porteur ou venus par la présentation publique. */
beforeEach(function () {
    $this->yao = person('Yao Kouassi', ['Couture']);
    $this->zumra = zumra($this->yao, 'Couture solidaire');
    $this->resp = person('Responsable Un', []);
    $this->awa = person('Awa Traoré', ['Broderie'], [], null, 'public');
    $this->koffi = person('Koffi Diallo', ['Tissage']);
    $this->dehors = person('Personne Extérieure', []);
    ajouteMembre($this->zumra, $this->resp, ZumraRole::Manager);
    ajouteMembre($this->zumra, $this->awa);
    ajouteMembre($this->zumra, $this->koffi);
    $this->projet = projet($this->yao, $this->zumra, 'Uniformes de la rentrée');
});

function equipe_monde(User $qui, Project $projet)
{
    return Livewire::actingAs($qui)->test(MondeProjet::class, ['project' => $projet])->set('tab', 'equipe');
}

function notifications_de(User $qui, NotificationKind $genre)
{
    return UserNotification::query()->where('user_id', $qui->id)->where('kind', $genre->value)->get();
}

function invite(User $porteur, Project $projet, User $personne, ?string $mot = null): ProjectInvitation
{
    return app(ProjectInvitations::class)->invite($porteur, $projet, $personne, $mot);
}

function publie(Project $projet, User $porteur): void
{
    app(ProjectShowcase::class)->publish($porteur, $projet, ['summary' => 'Coudre 200 uniformes.', 'contribute' => 'Offrez du temps.']);
}

// ─── Inviter : le consentement ───────────────────────────────────────────────

it('laisse le porteur inviter une personne, même hors de la ZUMRA, qui en est informée et n’entre qu’en acceptant', function () {
    $invitation = invite($this->yao, $this->projet, $this->dehors, 'Venez nous aider.');

    expect($invitation->state)->toBe(ProjectInvitation::PENDING)->and($invitation->invited_by)->toBe($this->yao->id)->and(ProjectMember::count())->toBe(0);
    $n = notifications_de($this->dehors, NotificationKind::ProjectInvited)->sole();
    expect($n->actor_id)->toBe($this->yao->id)->and($n->project_id)->toBe($this->projet->id);

    app(ProjectInvitations::class)->accept($this->dehors, $invitation);

    expect(ProjectMember::query()->where('user_id', $this->dehors->id)->exists())->toBeTrue()->and($invitation->fresh()->state)->toBe(ProjectInvitation::ACCEPTED);
    expect(notifications_de($this->yao, NotificationKind::ProjectInviteAccepted))->toHaveCount(1);
    expect($this->projet->privacyLabel())->toBe('Projet partagé');
});

it('laisse la personne refuser, sans prévenir le porteur, et le porteur annuler ; on peut réinviter ensuite', function () {
    $refusee = invite($this->yao, $this->projet, $this->awa);
    app(ProjectInvitations::class)->refuse($this->awa, $refusee);
    expect($refusee->fresh()->state)->toBe(ProjectInvitation::REFUSED)->and(ProjectMember::count())->toBe(0)->and(notifications_de($this->yao, NotificationKind::ProjectInviteAccepted))->toHaveCount(0);

    $annulee = invite($this->yao, $this->projet, $this->koffi);
    app(ProjectInvitations::class)->cancel($this->yao, $annulee);
    expect($annulee->fresh()->state)->toBe(ProjectInvitation::CANCELLED);
    expect(fn () => app(ProjectInvitations::class)->accept($this->koffi, $annulee->fresh()))->toThrow(ValidationException::class);

    $encore = invite($this->yao, $this->projet, $this->awa);
    expect($encore->id)->toBe($refusee->id)->and($encore->state)->toBe(ProjectInvitation::PENDING);
});

it('réserve l’invitation au porteur, et refuse une personne inexistante, non vérifiée, déjà dans l’équipe ou déjà invitée', function () {
    foreach ([$this->resp, $this->awa, $this->dehors] as $qui) {
        expect(fn () => invite($qui, $this->projet, $this->koffi))->toThrow(AuthorizationException::class);
    }
    $non = User::factory()->unverified()->create();
    expect(fn () => invite($this->yao, $this->projet, $non))->toThrow(ValidationException::class);
    expect(fn () => invite($this->yao, $this->projet, $this->yao))->toThrow(ValidationException::class);

    invite($this->yao, $this->projet, $this->awa);
    expect(fn () => invite($this->yao, $this->projet, $this->awa))->toThrow(ValidationException::class, 'déjà en attente');
    app(ProjectInvitations::class)->accept($this->awa, ProjectInvitation::query()->where('user_id', $this->awa->id)->sole());
    expect(fn () => invite($this->yao, $this->projet, $this->awa))->toThrow(ValidationException::class, 'collabore déjà');
    expect(fn () => invite($this->yao, $this->projet, $this->koffi, str_repeat('a', 281)))->toThrow(ValidationException::class);
});

it('ne laisse répondre qu’à la personne invitée', function () {
    $invitation = invite($this->yao, $this->projet, $this->awa);

    foreach ([$this->yao, $this->koffi, $this->dehors] as $intrus) {
        expect(fn () => app(ProjectInvitations::class)->accept($intrus, $invitation))->toThrow(HttpException::class);
    }
    expect(ProjectMember::count())->toBe(0);
});

it('garantit en base une seule ligne par personne et par projet, et la liste des états d’une invitation', function () {
    invite($this->yao, $this->projet, $this->awa);
    $ligne = fn (array $data) => $data + ['id' => (string) Str::uuid(), 'project_id' => $this->projet->id, 'user_id' => $this->koffi->id, 'state' => 'pending', 'created_at' => now(), 'updated_at' => now()];

    expect(fn () => DB::transaction(fn () => DB::table('project_invitations')->insert($ligne(['user_id' => $this->awa->id]))))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('project_invitations')->insert($ligne(['state' => 'inconnu']))))->toThrow(QueryException::class);
});

it('ne laisse plus ajouter quelqu’un sans son accord : aucune méthode ne le permet', function () {
    expect(method_exists(ProjectTeam::class, 'add'))->toBeFalse();
});

// ─── Retirer, quitter ────────────────────────────────────────────────────────

it('laisse le porteur retirer une personne de l’équipe, qui en est informée, mais pas un collaborateur ni un responsable de ZUMRA', function () {
    app(ProjectInvitations::class)->accept($this->awa, invite($this->yao, $this->projet, $this->awa));
    app(ProjectInvitations::class)->accept($this->koffi, invite($this->yao, $this->projet, $this->koffi));
    $ligne = ProjectMember::query()->where('user_id', $this->awa->id)->sole();

    foreach ([$this->koffi, $this->resp, $this->dehors] as $intrus) {
        expect(fn () => app(ProjectTeam::class)->remove($intrus, $ligne))->toThrow(AuthorizationException::class);
    }
    app(ProjectTeam::class)->remove($this->yao, $ligne);

    expect(ProjectMember::query()->where('user_id', $this->awa->id)->exists())->toBeFalse()->and(ProjectMember::count())->toBe(1);
    expect(notifications_de($this->awa, NotificationKind::ProjectMemberRemoved))->toHaveCount(1);
});

it('laisse une personne quitter l’équipe, sans prévenir personne', function () {
    app(ProjectInvitations::class)->accept($this->awa, invite($this->yao, $this->projet, $this->awa));

    app(ProjectTeam::class)->leave($this->awa, $this->projet);

    expect(ProjectMember::count())->toBe(0)->and(UserNotification::query()->where('user_id', $this->yao->id)->where('kind', NotificationKind::ProjectMemberRemoved->value)->count())->toBe(0);
    expect(fn () => app(ProjectTeam::class)->leave($this->awa, $this->projet))->toThrow(HttpException::class);
});

it('garde la collaboration d’une personne qui quitte la ZUMRA d’accueil : l’équipe ne dépend pas de la ZUMRA', function () {
    app(ProjectInvitations::class)->accept($this->awa, invite($this->yao, $this->projet, $this->awa));

    app(Memberships::class)->leave($this->awa, $this->zumra);

    expect(ProjectMember::query()->where('user_id', $this->awa->id)->exists())->toBeTrue();
});

it('supprime l’équipe, les invitations et les demandes avec le projet, et la ligne d’une personne avec son compte', function () {
    app(ProjectInvitations::class)->accept($this->awa, invite($this->yao, $this->projet, $this->awa));
    invite($this->yao, $this->projet, $this->koffi);
    publie($this->projet, $this->yao);
    app(ParticipationRequests::class)->propose($this->dehors, $this->projet);

    $this->awa->delete();
    expect(ProjectMember::count())->toBe(0);

    $this->projet->delete();
    expect(ProjectInvitation::count())->toBe(0)->and(ParticipationRequest::count())->toBe(0);
});

// ─── Demandes d'aide, par la présentation publique ───────────────────────────

it('laisse une personne vérifiée proposer son aide quand la présentation est publiée, et prévient le porteur seul', function () {
    publie($this->projet, $this->yao);

    $demande = app(ParticipationRequests::class)->propose($this->dehors, $this->projet, 'Je couds.');

    expect($demande->state)->toBe(JoinRequestState::Pending)->and($demande->message)->toBe('Je couds.');
    expect(notifications_de($this->yao, NotificationKind::ProjectParticipationRequested))->toHaveCount(1)
        ->and(notifications_de($this->resp, NotificationKind::ProjectParticipationRequested))->toHaveCount(0)
        ->and(notifications_de($this->dehors, NotificationKind::ProjectParticipationRequested))->toHaveCount(0);
});

it('refuse la demande sans présentation publique, au porteur, à un collaborateur, à une personne non vérifiée', function () {
    expect(fn () => app(ParticipationRequests::class)->propose($this->dehors, $this->projet))->toThrow(AuthorizationException::class);

    publie($this->projet, $this->yao);
    app(ProjectInvitations::class)->accept($this->awa, invite($this->yao, $this->projet, $this->awa));
    foreach ([$this->yao, $this->awa, User::factory()->unverified()->create()] as $qui) {
        expect(fn () => app(ParticipationRequests::class)->propose($qui, $this->projet))->toThrow(AuthorizationException::class);
    }
    expect(ParticipationRequest::count())->toBe(0);
});

it('n’accepte plus de demande pour un projet terminé ou abandonné, mais oui pour une idée ou un projet en cours', function () {
    publie($this->projet, $this->yao);
    $demande = fn () => app(ParticipationRequests::class)->propose($this->dehors, $this->projet->fresh());

    app(Projects::class)->changeState($this->yao, $this->projet, ProjectState::InProgress);
    expect($demande())->toBeInstanceOf(ParticipationRequest::class);
    app(ParticipationRequests::class)->withdraw($this->dehors, ParticipationRequest::sole());

    app(Projects::class)->changeState($this->yao, $this->projet, ProjectState::Done);
    expect($demande)->toThrow(AuthorizationException::class);
});

it('vérifie le mot (280 caractères au plus), le reprend après un retrait, et refuse deux demandes en attente ou une demande déclinée', function () {
    publie($this->projet, $this->yao);
    expect(fn () => app(ParticipationRequests::class)->propose($this->dehors, $this->projet, str_repeat('a', 281)))->toThrow(ValidationException::class);

    $premiere = app(ParticipationRequests::class)->propose($this->dehors, $this->projet, 'Premier mot');
    expect(fn () => app(ParticipationRequests::class)->propose($this->dehors, $this->projet))->toThrow(AuthorizationException::class);

    app(ParticipationRequests::class)->withdraw($this->dehors, $premiere);
    $seconde = app(ParticipationRequests::class)->propose($this->dehors, $this->projet, 'Second mot');
    expect($seconde->id)->toBe($premiere->id)->and($seconde->message)->toBe('Second mot')->and(ParticipationRequest::count())->toBe(1);

    app(ParticipationRequests::class)->decline($this->yao, $seconde);
    expect(fn () => app(ParticipationRequests::class)->propose($this->dehors, $this->projet))->toThrow(AuthorizationException::class);
});

it('limite le nombre de demandes d’aide par personne et par jour', function () {
    config(['gamad.limits.participation_requests_per_day' => 2]);
    foreach (['Un', 'Deux', 'Trois'] as $nom) {
        publie(projet($this->yao, $this->zumra, "Projet $nom"), $this->yao);
    }

    $projets = Project::query()->whereIn('name', ['Projet Un', 'Projet Deux', 'Projet Trois'])->orderBy('name')->get();
    app(ParticipationRequests::class)->propose($this->dehors, $projets[0]);
    app(ParticipationRequests::class)->propose($this->dehors, $projets[2]);
    expect(fn () => app(ParticipationRequests::class)->propose($this->dehors, $projets[1]))->toThrow(ValidationException::class, 'limite');
});

it('ne laisse retirer sa demande qu’à la personne qui l’a faite, tant qu’elle est en attente', function () {
    publie($this->projet, $this->yao);
    $demande = app(ParticipationRequests::class)->propose($this->dehors, $this->projet);

    expect(fn () => app(ParticipationRequests::class)->withdraw($this->awa, $demande))->toThrow(HttpException::class);
    app(ParticipationRequests::class)->withdraw($this->dehors, $demande);
    expect(fn () => app(ParticipationRequests::class)->withdraw($this->dehors, $demande->fresh()))->toThrow(ValidationException::class);
});

it('accepte une demande : la personne entre dans l’équipe, en est informée, et la demande est close', function () {
    publie($this->projet, $this->yao);
    $demande = app(ParticipationRequests::class)->propose($this->dehors, $this->projet);

    app(ParticipationRequests::class)->accept($this->yao, $demande);

    expect($demande->fresh()->state)->toBe(JoinRequestState::Accepted)->and($demande->fresh()->decided_by)->toBe($this->yao->id)
        ->and(ProjectMember::query()->where('user_id', $this->dehors->id)->exists())->toBeTrue();
    expect(notifications_de($this->dehors, NotificationKind::ProjectParticipationAccepted))->toHaveCount(1);
});

it('décline une demande sans prévenir la personne, et refuse un traitement double', function () {
    publie($this->projet, $this->yao);
    $demande = app(ParticipationRequests::class)->propose($this->dehors, $this->projet);

    app(ParticipationRequests::class)->decline($this->yao, $demande);

    expect($demande->fresh()->state)->toBe(JoinRequestState::Declined)->and(UserNotification::query()->where('user_id', $this->dehors->id)->count())->toBe(0);
    expect(fn () => app(ParticipationRequests::class)->decline($this->yao, $demande->fresh()))->toThrow(ValidationException::class);
    expect(fn () => app(ParticipationRequests::class)->accept($this->yao, $demande->fresh()))->toThrow(ValidationException::class);
});

it('réserve le traitement des demandes au porteur : ni un responsable de la ZUMRA, ni un collaborateur, ni un tiers', function () {
    publie($this->projet, $this->yao);
    app(ProjectInvitations::class)->accept($this->awa, invite($this->yao, $this->projet, $this->awa));
    $demande = app(ParticipationRequests::class)->propose($this->dehors, $this->projet);

    foreach ([$this->resp, $this->awa, $this->koffi] as $qui) {
        expect(fn () => app(ParticipationRequests::class)->accept($qui, $demande))->toThrow(AuthorizationException::class);
        expect(fn () => app(ParticipationRequests::class)->decline($qui, $demande))->toThrow(AuthorizationException::class);
    }
    expect($demande->fresh()->state)->toBe(JoinRequestState::Pending);
});

it('garantit en base la liste des états d’une demande, et une ligne par personne et par projet', function () {
    publie($this->projet, $this->yao);
    app(ParticipationRequests::class)->propose($this->dehors, $this->projet);
    $ligne = fn (array $data) => $data + ['id' => (string) Str::uuid(), 'project_id' => $this->projet->id, 'user_id' => $this->koffi->id, 'state' => 'pending', 'created_at' => now(), 'updated_at' => now()];

    expect(fn () => DB::transaction(fn () => DB::table('participation_requests')->insert($ligne(['user_id' => $this->dehors->id]))))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('participation_requests')->insert($ligne(['state' => 'inconnu']))))->toThrow(QueryException::class);
});

// ─── Écrans ──────────────────────────────────────────────────────────────────

it('montre l’équipe au porteur et aux collaborateurs seulement, par ordre alphabétique', function () {
    app(ProjectInvitations::class)->accept($this->koffi, invite($this->yao, $this->projet, $this->koffi));
    app(ProjectInvitations::class)->accept($this->awa, invite($this->yao, $this->projet, $this->awa));

    foreach ([$this->yao, $this->awa] as $qui) {
        $html = equipe_monde($qui, $this->projet)->assertSeeText('2 personnes participent')->assertSeeText('Awa Traoré')->assertSeeText('Koffi Diallo')->html();
        $equipe = substr($html, strpos($html, 'Équipe du projet'));
        expect(strpos($equipe, 'Awa Traoré'))->toBeLessThan(strpos($equipe, 'Koffi Diallo'));
    }
    foreach ([$this->resp, $this->dehors] as $qui) {
        $this->actingAs($qui)->get(route('projets.show', $this->projet))->assertNotFound();
    }
    app('auth')->forgetGuards();
    $this->get(route('projets.show', $this->projet))->assertNotFound();
});

it('ne compte pas, dans l’équipe, une personne dont l’adresse n’est plus vérifiée', function () {
    app(ProjectInvitations::class)->accept($this->awa, invite($this->yao, $this->projet, $this->awa));
    $this->awa->forceFill(['email_verified_at' => null])->save();

    equipe_monde($this->yao, $this->projet)->assertSeeText('0 personne participe')->assertDontSeeText('Awa Traoré');
});

it('invite, annule, quitte l’équipe et propose son aide depuis les écrans', function () {
    $c = equipe_monde($this->yao, $this->projet)->set('inviteQuery', 'Awa')->assertSeeText('Awa Traoré')
        ->set('inviteWord', 'Rejoignez-nous.')->call('invite', $this->awa->id)->assertHasNoErrors();
    expect(ProjectInvitation::sole()->message)->toBe('Rejoignez-nous.');
    $c->set('tab', 'equipe')->assertSeeText('Invitations en attente')->call('cancelInvitation', ProjectInvitation::sole()->id);
    expect(ProjectInvitation::sole()->state)->toBe(ProjectInvitation::CANCELLED);

    invite($this->yao, $this->projet, $this->koffi);
    Livewire::actingAs($this->koffi)->test(MesProjets::class)->assertSeeText('Invitations à collaborer')->assertSeeText('Uniformes de la rentrée')->call('accept', ProjectInvitation::query()->where('user_id', $this->koffi->id)->sole()->id)
        ->assertSeeText('Mes collaborations');

    equipe_monde($this->koffi, $this->projet)->assertSeeText('Quitter l’équipe')->call('leaveTeam');
    expect(ProjectMember::count())->toBe(0);

    publie($this->projet, $this->yao);
    Livewire::actingAs($this->dehors)->test(ProjetVitrine::class, ['project' => $this->projet])->assertSeeText('Proposer mon aide')
        ->set('participationWord', 'Je couds.')->call('requestParticipation')->assertSeeText('Demande envoyée')->assertSeeText('Retirer ma demande')
        ->call('withdrawParticipation');
    expect(ParticipationRequest::sole()->state)->toBe(JoinRequestState::Withdrawn);
});

it('affiche l’erreur d’un mot trop long sous le champ', function () {
    publie($this->projet, $this->yao);

    Livewire::actingAs($this->dehors)->test(ProjetVitrine::class, ['project' => $this->projet])->set('participationWord', str_repeat('a', 281))->call('requestParticipation')->assertHasErrors('participationWord');
});

it('montre au porteur les demandes d’aide à traiter, et les traite depuis l’écran', function () {
    publie($this->projet, $this->yao);
    app(ParticipationRequests::class)->propose($this->dehors, $this->projet, 'Je couds.');
    app(ParticipationRequests::class)->propose($this->koffi, $this->projet);

    $c = equipe_monde($this->yao, $this->projet)->assertSeeText('Demandes d’aide')->assertSeeText('Personne Extérieure')->assertSeeText('Je couds.');
    $c->call('acceptRequest', ParticipationRequest::query()->where('user_id', $this->dehors->id)->sole()->id)->call('declineRequest', ParticipationRequest::query()->where('user_id', $this->koffi->id)->sole()->id)->assertSeeText('Aucune demande en attente.');

    expect(ProjectMember::query()->where('user_id', $this->dehors->id)->exists())->toBeTrue()->and(ParticipationRequest::query()->where('user_id', $this->koffi->id)->sole()->state)->toBe(JoinRequestState::Declined);
});

it('ne touche ni un membre ni une demande d’un autre projet, ni un identifiant qui n’en est pas un', function () {
    $autre = projet($this->yao, $this->zumra, 'Autre projet');
    app(ProjectInvitations::class)->accept($this->awa, invite($this->yao, $autre, $this->awa));
    publie($autre, $this->yao);
    $demande = app(ParticipationRequests::class)->propose($this->dehors, $autre);

    equipe_monde($this->yao, $this->projet)->call('removeMember', ProjectMember::sole()->id)->assertNotFound();
    equipe_monde($this->yao, $this->projet)->call('acceptRequest', $demande->id)->assertNotFound();
    equipe_monde($this->yao, $this->projet)->call('declineRequest', $demande->id)->assertNotFound();
    equipe_monde($this->yao, $this->projet)->call('removeMember', 'pas-un-uuid')->assertNotFound();
    equipe_monde($this->yao, $this->projet)->call('cancelInvitation', 'pas-un-uuid')->assertNotFound();

    expect(ProjectMember::count())->toBe(1)->and($demande->fresh()->state)->toBe(JoinRequestState::Pending);
});

it('mène les notifications du projet au bon écran, et nomme le projet', function () {
    invite($this->yao, $this->projet, $this->awa);
    publie($this->projet, $this->yao);
    app(ParticipationRequests::class)->propose($this->dehors, $this->projet);

    $invitation = notifications_de($this->awa, NotificationKind::ProjectInvited)->sole();
    Livewire::actingAs($this->awa)->test(PageNotifications::class)->assertSeeText('vous invite à collaborer au projet')->assertSeeText('Uniformes de la rentrée');
    $this->actingAs($this->awa)->get(route('notifications.open', $invitation))->assertRedirect(route('espace.projets'));

    $demande = notifications_de($this->yao, NotificationKind::ProjectParticipationRequested)->sole();
    $this->actingAs($this->yao)->get(route('notifications.open', $demande))->assertRedirect(route('projets.show', ['project' => $this->projet, 'onglet' => 'equipe']));
});

it('ne laisse pas ouvrir la notification d’une autre personne, et revient à la liste si le projet n’existe plus', function () {
    invite($this->yao, $this->projet, $this->awa);
    $notification = notifications_de($this->awa, NotificationKind::ProjectInvited)->sole();

    $this->actingAs($this->dehors)->get(route('notifications.open', $notification))->assertNotFound();

    // Les notifications d'un projet disparaissent avec lui.
    $this->projet->delete();
    expect(UserNotification::query()->whereKey($notification->id)->exists())->toBeFalse();
});

it('affiche dans « À faire maintenant » du porteur les demandes d’aide à traiter, et à la personne invitée son invitation', function () {
    publie($this->projet, $this->yao);
    app(ParticipationRequests::class)->propose($this->dehors, $this->projet);
    app(ParticipationRequests::class)->propose($this->koffi, $this->projet);
    invite($this->yao, $this->projet, $this->awa);

    $lignes = collect(app(Todo::class)->for($this->yao));
    expect($lignes->pluck('title')->implode(' '))->toContain('2 demandes de participation à traiter');
    expect(collect(app(Todo::class)->for($this->resp))->pluck('title')->implode(' '))->not->toContain('participation');
    expect(collect(app(Todo::class)->for($this->awa))->pluck('title')->implode(' '))->toContain('Invitation à collaborer au projet « Uniformes de la rentrée »');
});
