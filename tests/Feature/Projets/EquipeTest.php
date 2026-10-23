<?php

use App\Enums\JoinRequestState;
use App\Enums\NotificationKind;
use App\Enums\ProjectState;
use App\Enums\ZumraRole;
use App\Livewire\Espace;
use App\Livewire\MondeProjet;
use App\Livewire\PageNotifications;
use App\Models\ParticipationRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\ZumraMembership;
use App\Services\Memberships;
use App\Services\ParticipationRequests;
use App\Services\Projects;
use App\Services\ProjectTeam;
use App\Services\Todo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

function equipe_monde(?User $qui, Project $projet)
{
    return ($qui ? Livewire::actingAs($qui) : new class
    {
        public function __call($m, $a)
        {
            return Livewire::$m(...$a);
        }
    })->test(MondeProjet::class, ['project' => $projet])->set('tab', 'equipe');
}

function notifications_de(User $qui, NotificationKind $genre)
{
    return UserNotification::query()->where('user_id', $qui->id)->where('kind', $genre->value)->get();
}

// ─── Ajouter à l'équipe ──────────────────────────────────────────────────────

it('laisse le fondateur et les responsables ajouter un membre de la ZUMRA à l’équipe, qui en est informé', function () {
    $m = app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);
    app(ProjectTeam::class)->add($this->resp, $this->projet, $this->koffi);

    expect($m->project_id)->toBe($this->projet->id)->and($m->user_id)->toBe($this->awa->id)->and($m->added_by)->toBe($this->yao->id)
        ->and(ProjectMember::count())->toBe(2);

    $n = notifications_de($this->awa, NotificationKind::ProjectMemberAdded)->sole();
    expect($n->actor_id)->toBe($this->yao->id)->and($n->project_id)->toBe($this->projet->id);
    expect(notifications_de($this->yao, NotificationKind::ProjectMemberAdded))->toHaveCount(0);
});

it('laisse un responsable s’ajouter lui-même, sans notification', function () {
    app(ProjectTeam::class)->add($this->resp, $this->projet, $this->resp);

    expect(ProjectMember::sole()->user_id)->toBe($this->resp->id);
    expect(UserNotification::count())->toBe(0);
});

it('refuse d’ajouter une personne qui n’est pas membre de la ZUMRA, ou dont l’adresse n’est pas vérifiée, ou déjà dans l’équipe', function () {
    expect(fn () => app(ProjectTeam::class)->add($this->yao, $this->projet, $this->dehors))->toThrow(ValidationException::class, 'membres de la ZUMRA');

    $discret = User::factory()->unverified()->create();
    ajouteMembre($this->zumra, $discret);
    expect(fn () => app(ProjectTeam::class)->add($this->yao, $this->projet, $discret))->toThrow(ValidationException::class);

    app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);
    expect(fn () => app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa))->toThrow(ValidationException::class, 'déjà dans l’équipe');

    expect(ProjectMember::count())->toBe(1);
});

it('refuse l’ajout à un membre simple, à une personne extérieure', function () {
    foreach ([$this->awa, $this->dehors] as $qui) {
        expect(fn () => app(ProjectTeam::class)->add($qui, $this->projet, $this->koffi))->toThrow(AuthorizationException::class);
    }
    expect(ProjectMember::count())->toBe(0);
});

it('clôt la demande en attente d’une personne qu’on ajoute à l’équipe', function () {
    $demande = app(ParticipationRequests::class)->propose($this->awa, $this->projet, 'Je coud.');

    app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);

    expect($demande->fresh()->state)->toBe(JoinRequestState::Accepted)->and($demande->fresh()->decided_by)->toBe($this->yao->id);
});

it('garantit en base une seule ligne par personne et par projet', function () {
    app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);

    expect(fn () => DB::transaction(fn () => DB::table('project_members')->insert([
        'id' => (string) Str::uuid(), 'project_id' => $this->projet->id, 'user_id' => $this->awa->id, 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class);
});

// ─── Retirer, quitter ────────────────────────────────────────────────────────

it('laisse les gestionnaires retirer une personne de l’équipe, qui en est informée, mais pas un membre simple', function () {
    $m = app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);

    foreach ([$this->koffi, $this->dehors, $this->awa] as $qui) {
        expect(fn () => app(ProjectTeam::class)->remove($qui, $m->fresh()))->toThrow(AuthorizationException::class);
    }
    expect(ProjectMember::count())->toBe(1);

    app(ProjectTeam::class)->remove($this->resp, $m->fresh());

    expect(ProjectMember::count())->toBe(0);
    expect(notifications_de($this->awa, NotificationKind::ProjectMemberRemoved)->sole()->actor_id)->toBe($this->resp->id);
});

it('laisse une personne quitter l’équipe, sans prévenir personne', function () {
    app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);
    UserNotification::query()->delete();

    app(ProjectTeam::class)->leave($this->awa, $this->projet);

    expect(ProjectMember::count())->toBe(0)->and(UserNotification::count())->toBe(0);
    expect(fn () => app(ProjectTeam::class)->leave($this->koffi, $this->projet))->toThrow(HttpException::class);
});

it('sort de l’équipe des projets, et clôt les demandes en attente, quand on quitte la ZUMRA', function () {
    $autre = projet($this->yao, $this->zumra, 'Autre projet');
    app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);
    app(ParticipationRequests::class)->propose($this->awa, $autre);
    $voisine = zumra($this->dehors, 'Autre ZUMRA');
    ajouteMembre($voisine, $this->awa);
    $projetVoisin = projet($this->dehors, $voisine, 'Projet voisin');
    app(ProjectTeam::class)->add($this->dehors, $projetVoisin, $this->awa);

    app(Memberships::class)->leave($this->awa, $this->zumra);

    expect(ProjectMember::where('user_id', $this->awa->id)->pluck('project_id')->all())->toBe([$projetVoisin->id]);
    expect(ParticipationRequest::where('project_id', $autre->id)->sole()->state)->toBe(JoinRequestState::Withdrawn);
});

it('sort aussi de l’équipe quand un gestionnaire retire la personne de la ZUMRA', function () {
    app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);

    app(Memberships::class)->remove($this->yao, $this->zumra, ligne($this->zumra, $this->awa));

    expect(ProjectMember::count())->toBe(0);
});

it('supprime l’équipe et les demandes avec le projet, et la ligne d’une personne avec son compte', function () {
    app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);
    app(ParticipationRequests::class)->propose($this->koffi, $this->projet);

    $this->awa->delete();
    expect(ProjectMember::count())->toBe(0);

    $this->projet->delete();
    expect(ParticipationRequest::count())->toBe(0);
});

// ─── Demande « Je veux participer » ──────────────────────────────────────────

it('laisse un membre de la ZUMRA demander à participer, et prévient le fondateur et les responsables, pas lui-même', function () {
    $d = app(ParticipationRequests::class)->propose($this->awa, $this->projet, 'Je sais coudre des uniformes.');

    expect($d->state)->toBe(JoinRequestState::Pending)->and($d->message)->toBe('Je sais coudre des uniformes.');

    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        $n = notifications_de($gestionnaire, NotificationKind::ProjectParticipationRequested)->sole();
        expect($n->actor_id)->toBe($this->awa->id)->and($n->project_id)->toBe($this->projet->id);
    }
    expect(UserNotification::query()->whereIn('user_id', [$this->awa->id, $this->koffi->id])->count())->toBe(0);
});

it('refuse la demande à qui ne peut pas participer, avec la raison', function () {
    $verifiee = fn ($qui) => Gate::forUser($qui)->inspect('participate', $this->projet);

    expect($verifiee($this->dehors)->message())->toContain('rejoignez d’abord la ZUMRA')
        ->and($verifiee($this->yao)->message())->toContain('ajoutez-vous directement')
        ->and($verifiee($this->resp)->message())->toContain('ajoutez-vous directement');

    $non = User::factory()->unverified()->create();
    ajouteMembre($this->zumra, $non);
    expect($verifiee($non)->message())->toContain('Vérifiez votre adresse e-mail');

    app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);
    expect($verifiee($this->awa)->message())->toContain('déjà dans l’équipe');

    foreach ([$this->dehors, $this->yao, $non, $this->awa] as $qui) {
        expect(fn () => app(ParticipationRequests::class)->propose($qui, $this->projet))->toThrow(AuthorizationException::class);
    }
    expect(Gate::forUser(null)->allows('participate', $this->projet))->toBeFalse();
    expect(ParticipationRequest::count())->toBe(0);
});

it('n’accepte plus de demande pour un projet terminé ou abandonné, mais oui pour une idée ou un projet en cours', function () {
    expect(Gate::forUser($this->awa)->allows('participate', $this->projet))->toBeTrue();
    app(Projects::class)->changeState($this->yao, $this->projet, ProjectState::InProgress);
    expect(Gate::forUser($this->awa)->allows('participate', $this->projet->fresh()))->toBeTrue();

    app(Projects::class)->changeState($this->yao, $this->projet, ProjectState::Done);
    expect(Gate::forUser($this->awa)->inspect('participate', $this->projet->fresh())->message())->toContain('n’accepte plus de participation');

    app(Projects::class)->changeState($this->yao, $this->projet, ProjectState::InProgress);
    app(Projects::class)->changeState($this->yao, $this->projet, ProjectState::Abandoned);
    expect(fn () => app(ParticipationRequests::class)->propose($this->awa, $this->projet->fresh()))->toThrow(AuthorizationException::class);
});

it('vérifie le mot (280 caractères au plus), le reprend après un retrait, et refuse deux demandes en attente ou une demande déclinée', function () {
    expect(fn () => app(ParticipationRequests::class)->propose($this->awa, $this->projet, str_repeat('é', 281)))->toThrow(ValidationException::class);
    expect(app(ParticipationRequests::class)->propose($this->awa, $this->projet, str_repeat('é', 280))->message)->toHaveLength(280);

    expect(fn () => app(ParticipationRequests::class)->propose($this->awa, $this->projet))->toThrow(AuthorizationException::class, 'déjà envoyée');

    app(ParticipationRequests::class)->withdraw($this->awa, ParticipationRequest::sole());
    expect(ParticipationRequest::sole()->state)->toBe(JoinRequestState::Withdrawn);

    $d = app(ParticipationRequests::class)->propose($this->awa, $this->projet);
    expect($d->state)->toBe(JoinRequestState::Pending)->and($d->message)->toBeNull()->and(ParticipationRequest::count())->toBe(1);

    app(ParticipationRequests::class)->decline($this->yao, $d);
    expect(fn () => app(ParticipationRequests::class)->propose($this->awa, $this->projet))->toThrow(AuthorizationException::class, 'pas été retenue');
});

it('limite le nombre de demandes de participation par personne et par jour', function () {
    config(['gamad.limits.participation_requests_per_day' => 2]);
    $a = projet($this->yao, $this->zumra, 'Projet A');
    $b = projet($this->yao, $this->zumra, 'Projet B');

    app(ParticipationRequests::class)->propose($this->awa, $this->projet);
    app(ParticipationRequests::class)->propose($this->awa, $a);
    expect(fn () => app(ParticipationRequests::class)->propose($this->awa, $b))->toThrow(ValidationException::class, 'limite');
    expect(app(ParticipationRequests::class)->propose($this->koffi, $b)->project_id)->toBe($b->id);

    $this->travel(25)->hours();
    expect(app(ParticipationRequests::class)->propose($this->awa, $b)->project_id)->toBe($b->id);
});

it('ne laisse retirer sa demande qu’à la personne qui l’a faite, tant qu’elle est en attente', function () {
    $d = app(ParticipationRequests::class)->propose($this->awa, $this->projet);

    expect(fn () => app(ParticipationRequests::class)->withdraw($this->koffi, $d))->toThrow(HttpException::class);
    app(ParticipationRequests::class)->decline($this->yao, $d);
    expect(fn () => app(ParticipationRequests::class)->withdraw($this->awa, $d->fresh()))->toThrow(ValidationException::class, 'ne peut plus être retirée');
});

it('accepte une demande : la personne entre dans l’équipe, en est informée, et la demande est close', function () {
    $d = app(ParticipationRequests::class)->propose($this->awa, $this->projet);

    app(ParticipationRequests::class)->accept($this->resp, $d);

    expect(ProjectMember::sole()->user_id)->toBe($this->awa->id)->and(ProjectMember::sole()->added_by)->toBeNull()
        ->and($d->fresh()->state)->toBe(JoinRequestState::Accepted)->and($d->fresh()->decided_by)->toBe($this->resp->id);
    expect(notifications_de($this->awa, NotificationKind::ProjectParticipationAccepted)->sole()->actor_id)->toBe($this->resp->id);

    expect(fn () => app(ParticipationRequests::class)->accept($this->yao, $d->fresh()))->toThrow(ValidationException::class, 'déjà été traitée');
});

it('décline une demande sans prévenir la personne, et refuse un traitement double', function () {
    $d = app(ParticipationRequests::class)->propose($this->awa, $this->projet);
    UserNotification::query()->delete();

    app(ParticipationRequests::class)->decline($this->yao, $d);

    expect($d->fresh()->state)->toBe(JoinRequestState::Declined)->and(UserNotification::count())->toBe(0)->and(ProjectMember::count())->toBe(0);
    expect(fn () => app(ParticipationRequests::class)->decline($this->yao, $d->fresh()))->toThrow(ValidationException::class);
    expect(fn () => app(ParticipationRequests::class)->accept($this->yao, $d->fresh()))->toThrow(ValidationException::class);
});

it('refuse de traiter une demande à un membre simple ou à une personne extérieure', function () {
    $d = app(ParticipationRequests::class)->propose($this->awa, $this->projet);

    foreach ([$this->koffi, $this->dehors, $this->awa] as $qui) {
        expect(fn () => app(ParticipationRequests::class)->accept($qui, $d->fresh()))->toThrow(AuthorizationException::class);
        expect(fn () => app(ParticipationRequests::class)->decline($qui, $d->fresh()))->toThrow(AuthorizationException::class);
    }
    expect($d->fresh()->state)->toBe(JoinRequestState::Pending)->and(ProjectMember::count())->toBe(0);
});

it('refuse d’accepter la demande d’une personne qui a quitté la ZUMRA depuis', function () {
    $d = app(ParticipationRequests::class)->propose($this->awa, $this->projet);
    ParticipationRequest::query()->whereKey($d->id)->update(['state' => 'pending']);
    ZumraMembership::query()->where('user_id', $this->awa->id)->delete();

    expect(fn () => app(ParticipationRequests::class)->accept($this->yao, $d->fresh()))->toThrow(ValidationException::class, 'n’est plus dans la ZUMRA');
    expect(ProjectMember::count())->toBe(0);
});

it('garantit en base la liste des états d’une demande, et une ligne par personne et par projet', function () {
    $d = app(ParticipationRequests::class)->propose($this->awa, $this->projet);
    $ligne = fn (array $data) => $data + ['id' => (string) Str::uuid(), 'project_id' => $this->projet->id, 'user_id' => $this->koffi->id, 'state' => 'pending', 'created_at' => now(), 'updated_at' => now()];

    expect(fn () => DB::transaction(fn () => DB::table('participation_requests')->insert($ligne(['state' => 'inconnu']))))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('participation_requests')->insert($ligne(['user_id' => $d->user_id]))))->toThrow(QueryException::class);
});

// ─── Onglet Équipe ───────────────────────────────────────────────────────────

it('montre à un visiteur sans compte le nombre de personnes de l’équipe, pas leurs noms', function () {
    app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);

    equipe_monde(null, $this->projet)->assertSeeText('1 personne participe')->assertDontSeeText('Awa Traoré')->assertSeeText('visibles des personnes inscrites')
        ->assertSeeText('Se connecter')->assertSeeText('Connectez-vous pour participer');
});

it('montre à une personne connectée et vérifiée les personnes de l’équipe, selon la visibilité de leur profil, par ordre alphabétique', function () {
    $discret = person('Zoé Discrète', [], [], null, 'members');
    ajouteMembre($this->zumra, $discret);
    foreach ([$this->koffi, $this->awa, $discret] as $p) {
        app(ProjectTeam::class)->add($this->yao, $this->projet, $p);
    }

    $html = equipe_monde($this->dehors, $this->projet)->assertSeeText('3 personnes participent')->assertSeeText('Awa Traoré')->assertSeeText('Koffi Diallo')->assertSeeText('Zoé Discrète')->html();
    expect(strpos($html, 'Awa Traoré'))->toBeLessThan(strpos($html, 'Koffi Diallo'))->and(strpos($html, 'Koffi Diallo'))->toBeLessThan(strpos($html, 'Zoé Discrète'));
    expect($html)->toContain(route('personnes.show', $this->awa->profile->public_id));
});

it('ne compte pas, dans l’équipe, une personne dont l’adresse n’est plus vérifiée', function () {
    app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);
    $this->awa->forceFill(['email_verified_at' => null])->save();

    equipe_monde($this->dehors, $this->projet)->assertSeeText('0 personne participe')->assertDontSeeText('Awa Traoré');
});

it('affiche à chacun le bon encadré de participation', function () {
    equipe_monde($this->awa, $this->projet)->assertSeeText('Participer à ce projet')->assertSeeText('Je veux participer')->assertSeeText('Un mot pour les responsables');
    equipe_monde($this->dehors, $this->projet)->assertSeeText('rejoignez d’abord la ZUMRA')->assertSeeText('Voir la ZUMRA')->assertDontSeeText('Un mot pour les responsables');
    equipe_monde($this->yao, $this->projet)->assertSeeText('ajoutez-vous directement')->assertDontSeeText('Un mot pour les responsables');

    app(ParticipationRequests::class)->propose($this->awa, $this->projet);
    equipe_monde($this->awa, $this->projet)->assertSeeText('Demande envoyée')->assertSeeText('Retirer ma demande')->assertDontSeeText('Un mot pour les responsables');

    app(ParticipationRequests::class)->decline($this->yao, ParticipationRequest::sole());
    equipe_monde($this->awa, $this->projet)->assertSeeText('pas été retenue')->assertDontSeeText('Retirer ma demande');

    app(ProjectTeam::class)->add($this->yao, $this->projet, $this->koffi);
    equipe_monde($this->koffi, $this->projet)->assertSeeText('Vous êtes dans l’équipe')->assertSeeText('Quitter l’équipe');
});

it('demande, retire sa demande et quitte l’équipe depuis l’écran', function () {
    equipe_monde($this->awa, $this->projet)->set('participationWord', 'Je veux aider.')->call('requestParticipation')->assertHasNoErrors()
        ->assertSeeText('Demande envoyée')->assertSet('participationWord', '')
        ->call('withdrawParticipation')->assertSeeText('Demande retirée')->assertSeeText('Je veux participer');

    expect(ParticipationRequest::sole()->state)->toBe(JoinRequestState::Withdrawn);

    app(ProjectTeam::class)->add($this->yao, $this->projet, $this->awa);
    equipe_monde($this->awa, $this->projet)->call('leaveTeam')->assertSeeText('Vous avez quitté l’équipe');
    expect(ProjectMember::count())->toBe(0);
});

it('affiche l’erreur d’un mot trop long sous le champ', function () {
    equipe_monde($this->awa, $this->projet)->set('participationWord', str_repeat('a', 281))->call('requestParticipation')->assertHasErrors(['participationWord']);
});

it('montre aux gestionnaires les demandes à traiter et les membres à ajouter, pas aux autres', function () {
    app(ParticipationRequests::class)->propose($this->awa, $this->projet, 'Je sais coudre.');

    foreach ([$this->yao, $this->resp] as $qui) {
        equipe_monde($qui, $this->projet)->assertSeeText('Demandes de participation')->assertSeeText('Je sais coudre.')->assertSeeText('Accepter')->assertSeeText('Décliner')
            ->assertSeeText('Ajouter des membres de la ZUMRA')->assertSeeText('Koffi Diallo');
    }
    foreach ([$this->awa, $this->koffi, $this->dehors, null] as $qui) {
        equipe_monde($qui, $this->projet)->assertDontSeeText('Demandes de participation')->assertDontSeeText('Ajouter des membres de la ZUMRA')->assertDontSeeText('Je sais coudre.');
    }
});

it('traite les demandes et gère l’équipe depuis l’écran, pour un gestionnaire seulement', function () {
    app(ParticipationRequests::class)->propose($this->awa, $this->projet);
    app(ParticipationRequests::class)->propose($this->koffi, $this->projet);
    [$demandeAwa, $demandeKoffi] = [ParticipationRequest::where('user_id', $this->awa->id)->first(), ParticipationRequest::where('user_id', $this->koffi->id)->first()];

    equipe_monde($this->resp, $this->projet)->call('acceptRequest', $demandeAwa->id)->assertSeeText('Awa Traoré')
        ->call('declineRequest', $demandeKoffi->id)->assertSeeText('Aucune demande en attente.');
    expect(ProjectMember::sole()->user_id)->toBe($this->awa->id)->and($demandeKoffi->fresh()->state)->toBe(JoinRequestState::Declined);

    equipe_monde($this->yao, $this->projet)->call('addMember', $this->koffi->id)->assertSeeText('2 personnes participent')
        ->call('removeMember', ProjectMember::where('user_id', $this->awa->id)->first()->id)->assertSeeText('1 personne participe');
    expect(ProjectMember::sole()->user_id)->toBe($this->koffi->id);

    foreach ([$this->awa, $this->dehors] as $qui) {
        equipe_monde($qui, $this->projet)->call('addMember', $this->resp->id)->assertNotFound();
        equipe_monde($qui, $this->projet)->call('removeMember', ProjectMember::first()->id)->assertNotFound();
        equipe_monde($qui, $this->projet)->call('acceptRequest', $demandeKoffi->id)->assertNotFound();
        equipe_monde($qui, $this->projet)->call('declineRequest', $demandeKoffi->id)->assertNotFound();
    }
    expect(ProjectMember::count())->toBe(1);
});

it('refuse l’ajout d’une personne qui n’est pas de la ZUMRA depuis l’écran, avec le message sous la liste', function () {
    equipe_monde($this->yao, $this->projet)->call('addMember', $this->dehors->id)->assertHasErrors(['team'])->assertSeeText('Seuls les membres de la ZUMRA');
    expect(ProjectMember::count())->toBe(0);
});

it('ne touche ni un membre ni une demande d’un autre projet, ni un identifiant qui n’en est pas un', function () {
    $autre = projet($this->yao, $this->zumra, 'Autre projet');
    $membre = app(ProjectTeam::class)->add($this->yao, $autre, $this->awa);
    $demande = app(ParticipationRequests::class)->propose($this->koffi, $autre);

    equipe_monde($this->yao, $this->projet)->call('removeMember', $membre->id)->assertNotFound();
    equipe_monde($this->yao, $this->projet)->call('acceptRequest', $demande->id)->assertNotFound();
    equipe_monde($this->yao, $this->projet)->call('declineRequest', 'pas-un-uuid')->assertNotFound();
    equipe_monde($this->yao, $this->projet)->call('addMember', 'pas-un-uuid')->assertNotFound();

    expect(ProjectMember::count())->toBe(1)->and($demande->fresh()->state)->toBe(JoinRequestState::Pending);
});

// ─── Notifications et « À faire maintenant » ─────────────────────────────────

it('mène les notifications du projet à son onglet Équipe, et nomme le projet', function () {
    app(ParticipationRequests::class)->propose($this->awa, $this->projet);
    $n = notifications_de($this->yao, NotificationKind::ProjectParticipationRequested)->sole();

    $this->actingAs($this->yao)->get(route('notifications.open', $n))->assertRedirect(route('projets.show', ['project' => $this->projet, 'onglet' => 'equipe']));
    expect($n->fresh()->read_at)->not->toBeNull();

    Livewire::actingAs($this->resp)->test(PageNotifications::class)->assertSeeText('Awa Traoré')->assertSeeText('veut participer au projet « Uniformes de la rentrée »');

    app(ParticipationRequests::class)->accept($this->yao, ParticipationRequest::sole());
    Livewire::actingAs($this->awa)->test(PageNotifications::class)->assertSeeText('a accepté votre participation au projet « Uniformes de la rentrée »');

    app(ProjectTeam::class)->remove($this->yao, ProjectMember::sole());
    Livewire::actingAs($this->awa)->test(PageNotifications::class)->assertSeeText('vous a retiré de l\'équipe du projet');
});

it('ne laisse pas ouvrir la notification d’une autre personne, et revient à la liste si le projet n’existe plus', function () {
    app(ParticipationRequests::class)->propose($this->awa, $this->projet);
    $n = notifications_de($this->yao, NotificationKind::ProjectParticipationRequested)->sole();

    $this->actingAs($this->awa)->get(route('notifications.open', $n))->assertNotFound();

    $this->projet->delete();
    expect(UserNotification::query()->whereKey($n->id)->exists())->toBeFalse();
});

it('affiche dans « À faire maintenant » des gestionnaires les demandes de participation à traiter, par projet', function () {
    $autre = projet($this->yao, $this->zumra, 'Autre projet');
    app(ParticipationRequests::class)->propose($this->awa, $this->projet);
    app(ParticipationRequests::class)->propose($this->koffi, $this->projet);
    app(ParticipationRequests::class)->propose($this->awa, $autre);

    foreach ([$this->yao, $this->resp] as $qui) {
        $lignes = collect(app(Todo::class)->for($qui))->pluck('title', 'key');
        expect($lignes['participation-'.$this->projet->id])->toBe('2 demandes de participation à traiter')
            ->and($lignes['participation-'.$autre->id])->toBe('1 demande de participation à traiter');
    }
    foreach ([$this->awa, $this->dehors] as $qui) {
        expect(collect(app(Todo::class)->for($qui))->keys()->filter(fn ($k) => str_starts_with($k, 'participation-')))->toBeEmpty();
    }

    app(ParticipationRequests::class)->decline($this->yao, ParticipationRequest::where('user_id', $this->koffi->id)->first());
    app(ParticipationRequests::class)->accept($this->yao, ParticipationRequest::where('user_id', $this->awa->id)->where('project_id', $this->projet->id)->first());
    expect(collect(app(Todo::class)->for($this->yao))->keys()->contains('participation-'.$this->projet->id))->toBeFalse();
    expect(collect(app(Todo::class)->for($this->yao))->pluck('url', 'key')['participation-'.$autre->id])->toContain('onglet=equipe');
});

it('affiche la ligne de demandes de participation dans Espace', function () {
    app(ParticipationRequests::class)->propose($this->awa, $this->projet);

    Livewire::actingAs($this->resp)->test(Espace::class)->assertSeeText('1 demande de participation à traiter')->assertSeeText('Uniformes de la rentrée');
});
