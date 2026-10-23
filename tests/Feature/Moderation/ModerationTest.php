<?php

use App\Enums\NotificationKind;
use App\Enums\PublicationType;
use App\Exceptions\ImmutableRecordException;
use App\Livewire\Espace;
use App\Livewire\Fil;
use App\Livewire\FileModeration;
use App\Livewire\PageNotifications;
use App\Livewire\PagePublication;
use App\Livewire\SignalerPublication;
use App\Models\AdminAction;
use App\Models\Publication;
use App\Models\Report;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Moderation;
use App\Services\Publications;
use App\Services\Reports;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->auteur = User::factory()->create(['name' => 'Awa Traoré']);
    $this->p1 = User::factory()->create(['name' => 'Yao Kouassi']);
    $this->p2 = User::factory()->create(['name' => 'Seydou Bamba']);
    $this->mod = User::factory()->moderator()->create(['name' => 'Modératrice Une']);
    $this->admin = User::factory()->superAdmin()->create(['name' => 'Président de test']);
    $this->atelier = publication($this->auteur, PublicationType::Knowledge, ['body' => 'Atelier ouvert à tous : tenir les comptes.']);
    app(Reports::class)->submit($this->p1, $this->atelier, 'scam', 'Demande de paiement avant l’atelier.');
    app(Reports::class)->submit($this->p2, $this->atelier, 'spam');
});

function moderation(): Moderation
{
    return app(Moderation::class);
}

function journal(string $action)
{
    return AdminAction::query()->where('action', $action)->get();
}

// ─── Accès ───────────────────────────────────────────────────────────────────

it('réserve la file de modération au super administrateur et aux modérateurs', function () {
    $this->get(route('moderation'))->assertRedirect(route('login'));
    $this->actingAs($this->p1)->get(route('moderation'))->assertForbidden();

    $this->actingAs($this->mod)->get(route('moderation'))->assertOk()->assertSeeText('Modération');
    $this->actingAs($this->admin)->get(route('moderation'))->assertOk();
});

it('refuse chaque acte de modération à une personne ordinaire, même en appelant le composant', function () {
    Livewire::actingAs($this->p1)->test(FileModeration::class)->assertForbidden();

    // Chaque acte du composant revérifie le droit de modérer, même si le composant a été monté par une autre session.
    $id = $this->atelier->id;
    $this->actingAs($this->p1);
    $composant = new FileModeration;
    $composant->reasons[$id] = 'Motif';

    foreach (['hide', 'dismiss', 'restore'] as $acte) {
        expect(fn () => $composant->{$acte}($id, moderation()))->toThrow(AuthorizationException::class);
    }

    foreach ([fn () => moderation()->hide($this->p1, $this->atelier, 'Motif'), fn () => moderation()->restore($this->p1, $this->atelier), fn () => moderation()->dismiss($this->p1, $this->atelier)] as $acte) {
        expect($acte)->toThrow(AuthorizationException::class);
    }

    expect($this->atelier->fresh()->isHidden())->toBeFalse()->and(AdminAction::query()->where('action', 'like', 'publication.%')->count())->toBe(0);
});

// ─── La file ─────────────────────────────────────────────────────────────────

it('liste la publication signalée une fois, avec ses signalements, sans dire qui a signalé', function () {
    $html = Livewire::actingAs($this->mod)->test(FileModeration::class)
        ->assertSeeText('Atelier ouvert à tous')->assertSeeText('Awa Traoré')->assertSeeText('2 SIGNALEMENTS')
        ->assertSeeText('Arnaque ou demande d’argent suspecte')->assertSeeText('Demande de paiement avant l’atelier.')->assertSeeText('Publicité ou spam')
        ->assertSeeText('À examiner (1)')->html();

    expect($html)->not->toContain('Yao Kouassi')->not->toContain('Seydou Bamba')->not->toContain($this->p1->email);
});

it('met en tête la publication dont le premier signalement est le plus ancien', function () {
    $recente = publication($this->auteur, data: ['body' => 'Besoin récent signalé']);
    app(Reports::class)->submit($this->p1, $recente, 'other');
    Report::query()->where('publication_id', $this->atelier->id)->update(['created_at' => now()->subDays(2)]);

    Livewire::actingAs($this->mod)->test(FileModeration::class)->assertSeeTextInOrder(['Atelier ouvert à tous', 'Besoin récent signalé']);
});

it('affiche l’état vide de chaque file', function () {
    Report::query()->delete();

    Livewire::actingAs($this->mod)->test(FileModeration::class)->assertSeeText('Aucun signalement à examiner.')
        ->call('setTab', 'masquees')->assertSeeText('Aucune publication masquée.');
});

// ─── Masquer ─────────────────────────────────────────────────────────────────

it('masque une publication avec un motif, clôt ses signalements et prévient son auteur', function () {
    Livewire::actingAs($this->mod)->test(FileModeration::class)
        ->set('reasons.'.$this->atelier->id, 'Demande de paiement anticipé non justifiée.')->call('hide', $this->atelier->id)
        ->assertHasNoErrors()->assertSeeText('Publication masquée')->assertSeeText('Aucun signalement à examiner.');

    $p = $this->atelier->fresh();
    expect($p->isHidden())->toBeTrue()->and($p->hidden_reason)->toBe('Demande de paiement anticipé non justifiée.')->and($p->hidden_by)->toBe($this->mod->id);

    expect(Report::query()->where('publication_id', $p->id)->get())->each(fn ($r) => $r->resolution->toBe('hidden')->and($r->resolved_by->toBe($this->mod->id)));

    $n = UserNotification::query()->where('user_id', $this->auteur->id)->where('kind', NotificationKind::PublicationHidden)->sole();
    expect($n->actor_id)->toBe($this->mod->id)->and($n->publication_id)->toBe($p->id);
});

it('inscrit le masquage au journal d’administration : acteur, sujet, motif', function () {
    moderation()->hide($this->mod, $this->atelier, 'Hameçonnage.');

    $a = journal('publication.hidden')->sole();
    expect($a->actor_id)->toBe($this->mod->id)->and($a->actor_label)->toBe('Modératrice Une')->and($a->subject_id)->toBe($this->atelier->id)
        ->and($a->subject_type)->toBe($this->atelier->getMorphClass())->and($a->reason)->toBe('Hameçonnage.')
        ->and($a->payload['author_id'])->toBe($this->auteur->id)->and($a->payload['reports_closed'])->toBe(2);
});

it('exige un motif, d’au plus 500 caractères, et n’écrit rien sans motif', function () {
    foreach ([null, '', '   '] as $vide) {
        expect(fn () => moderation()->hide($this->mod, $this->atelier, $vide))->toThrow(ValidationException::class);
    }
    expect(fn () => moderation()->hide($this->mod, $this->atelier, str_repeat('é', 501)))->toThrow(ValidationException::class);

    Livewire::actingAs($this->mod)->test(FileModeration::class)->call('hide', $this->atelier->id)->assertHasErrors(['reasons.'.$this->atelier->id]);

    expect($this->atelier->fresh()->isHidden())->toBeFalse()->and(journal('publication.hidden'))->toHaveCount(0)
        ->and(Report::query()->open()->count())->toBe(2);

    moderation()->hide($this->mod, $this->atelier, str_repeat('é', 500));
    expect($this->atelier->fresh()->isHidden())->toBeTrue();
});

it('laisse aussi le super administrateur masquer', function () {
    moderation()->hide($this->admin, $this->atelier, 'Motif');

    expect(journal('publication.hidden')->sole()->actor_id)->toBe($this->admin->id);
});

it('ne laisse pas modérer sa propre publication, ni masquer deux fois', function () {
    $sienne = publication($this->mod, data: ['body' => 'Publication de la modératrice']);

    expect(fn () => moderation()->hide($this->mod, $sienne, 'Motif'))->toThrow(ValidationException::class, 'propre publication');

    moderation()->hide($this->mod, $this->atelier, 'Motif');
    expect(fn () => moderation()->hide($this->admin, $this->atelier, 'Encore'))->toThrow(ValidationException::class, 'déjà masquée');
    expect(journal('publication.hidden'))->toHaveCount(1);
});

it('refuse un identifiant qui n’en est pas un, sans erreur de base de données', function () {
    Livewire::actingAs($this->mod)->test(FileModeration::class)->call('hide', 'pas-un-identifiant')->assertNotFound();
});

// ─── Classer sans suite ──────────────────────────────────────────────────────

it('classe les signalements sans suite : la publication reste visible, l’acte est tracé', function () {
    Livewire::actingAs($this->mod)->test(FileModeration::class)->set('reasons.'.$this->atelier->id, 'Rien d’anormal.')->call('dismiss', $this->atelier->id)->assertHasNoErrors()->assertSeeText('classés sans suite');

    expect($this->atelier->fresh()->isHidden())->toBeFalse()
        ->and(Report::query()->open()->count())->toBe(0)
        ->and(Report::query()->where('resolution', 'dismissed')->count())->toBe(2);

    $a = journal('report.dismissed')->sole();
    expect($a->actor_id)->toBe($this->mod->id)->and($a->reason)->toBe('Rien d’anormal.')->and($a->payload['reports_closed'])->toBe(2);

    // Rien à classer : refusé.
    expect(fn () => moderation()->dismiss($this->mod, $this->atelier))->toThrow(ValidationException::class);
    // L'auteur n'a pas été prévenu : un signalement classé sans suite ne le concerne pas.
    expect(UserNotification::query()->where('user_id', $this->auteur->id)->where('kind', NotificationKind::PublicationHidden)->count())->toBe(0);
});

it('un nouveau signalement après un classement rouvre la file', function () {
    moderation()->dismiss($this->mod, $this->atelier);
    app(Reports::class)->submit(User::factory()->create(), $this->atelier, 'offensive');

    Livewire::actingAs($this->mod)->test(FileModeration::class)->assertSeeText('1 SIGNALEMENT');
});

// ─── Rétablir ────────────────────────────────────────────────────────────────

it('rétablit une publication masquée, prévient l’auteur et inscrit l’acte au journal', function () {
    moderation()->hide($this->mod, $this->atelier, 'Motif');

    Livewire::actingAs($this->admin)->test(FileModeration::class)->call('setTab', 'masquees')
        ->assertSeeText('Atelier ouvert à tous')->assertSeeText('Motif :')
        ->set('reasons.'.$this->atelier->id, 'Explication reçue, tout est en ordre.')->call('restore', $this->atelier->id)->assertHasNoErrors()->assertSeeText('Aucune publication masquée.');

    $p = $this->atelier->fresh();
    expect($p->isHidden())->toBeFalse()->and($p->hidden_reason)->toBeNull()->and($p->hidden_by)->toBeNull();

    $a = journal('publication.restored')->sole();
    expect($a->actor_id)->toBe($this->admin->id)->and($a->reason)->toBe('Explication reçue, tout est en ordre.');
    expect(UserNotification::query()->where('user_id', $this->auteur->id)->where('kind', NotificationKind::PublicationRestored)->count())->toBe(1);
});

it('ne rétablit qu’une publication masquée, et pas la sienne', function () {
    expect(fn () => moderation()->restore($this->mod, $this->atelier))->toThrow(ValidationException::class, 'pas masquée');

    $sienne = publication($this->mod, data: ['body' => 'La mienne']);
    $sienne->forceFill(['hidden_at' => now(), 'hidden_reason' => 'Masquée par un autre'])->save();
    expect(fn () => moderation()->restore($this->mod, $sienne))->toThrow(ValidationException::class, 'propre publication');
});

it('ne laisse jamais le journal modifié ni effacé par un acte de modération', function () {
    moderation()->hide($this->mod, $this->atelier, 'Motif');
    $entree = journal('publication.hidden')->sole();

    expect(fn () => $entree->update(['reason' => 'Autre']))->toThrow(ImmutableRecordException::class)
        ->and(fn () => $entree->delete())->toThrow(ImmutableRecordException::class);
});

// ─── Effets d'une publication masquée ────────────────────────────────────────

it('retire la publication masquée du Fil, pour tout le monde y compris son auteur et la modération', function () {
    moderation()->hide($this->mod, $this->atelier, 'Motif');

    foreach ([$this->auteur, $this->mod, $this->admin, $this->p1] as $qui) {
        Livewire::actingAs($qui)->test(Fil::class)->assertDontSeeText('Atelier ouvert à tous');
    }
});

it('répond « introuvable » (404) à toute personne autre que l’auteur et la modération', function () {
    moderation()->hide($this->mod, $this->atelier, 'Motif');

    $this->actingAs($this->p1)->get(route('publications.show', $this->atelier))->assertNotFound();
    $this->actingAs(User::factory()->unverified()->create())->get(route('publications.show', $this->atelier))->assertNotFound();
    app('auth')->forgetGuards();
    $this->get(route('publications.show', $this->atelier))->assertNotFound();
    $this->actingAs($this->p1)->get(route('publications.signaler', $this->atelier))->assertNotFound();

    $this->actingAs($this->auteur)->get(route('publications.show', $this->atelier))->assertOk()->assertSeeText('Cette publication est masquée')->assertSeeText('Motif');
    $this->actingAs($this->mod)->get(route('publications.show', $this->atelier))->assertOk()->assertSeeText('Cette publication est masquée')->assertSee(route('moderation', ['onglet' => 'masquees']), false);
});

it('applique la même règle côté requête et côté politique, pour chaque spectateur', function () {
    moderation()->hide($this->mod, $this->atelier, 'Motif');
    $ouverte = publication($this->auteur, data: ['body' => 'Ouverte']);

    foreach ([null, $this->p1, $this->auteur, $this->mod, $this->admin, User::factory()->unverified()->create()] as $spectateur) {
        foreach ([$this->atelier, $ouverte] as $p) {
            $parLaRequete = Publication::query()->visibleTo($spectateur)->whereKey($p->id)->exists();
            $parLaPolitique = Gate::forUser($spectateur)->allows('view', $p->fresh());

            expect($parLaRequete)->toBe($parLaPolitique);
        }
    }
});

it('n’accepte plus de geste sur une publication masquée, ne la laisse plus résoudre, mais garde les conversations', function () {
    $besoin = publication($this->auteur, data: ['body' => 'Un besoin']);
    $conversation = conversationDe($this->auteur, $this->p1, $besoin);
    geste($this->p2, $besoin);

    moderation()->hide($this->mod, $besoin, 'Motif');
    $besoin = $besoin->fresh();

    expect($besoin->acceptsGestures())->toBeFalse();
    expect(fn () => geste(User::factory()->create(), $besoin))->toThrow(AuthorizationException::class);
    expect(fn () => app(Publications::class)->resolve($this->auteur, $besoin))->toThrow(AuthorizationException::class);
    expect($conversation->fresh())->not->toBeNull();

    // La conversation reste lisible et on peut y écrire : seule la publication est masquée.
    ecrire($this->p1, $conversation, 'Toujours là');
    expect($conversation->messages()->count())->toBe(1);
});

it('ne montre pas l’extrait d’une publication masquée dans une conversation à l’autre personne', function () {
    $besoin = publication($this->auteur, data: ['body' => 'Besoin masqué ensuite']);
    $conversation = conversationDe($this->auteur, $this->p1, $besoin);
    moderation()->hide($this->mod, $besoin, 'Motif');

    pageConversation($this->p1, $conversation)->assertDontSeeText('Besoin masqué ensuite');
});

// ─── Explication de l'auteur ─────────────────────────────────────────────────

it('laisse l’auteur écrire une explication tant que sa publication est masquée, et la modifier', function () {
    moderation()->hide($this->mod, $this->atelier, 'Demande de paiement.');

    Livewire::actingAs($this->auteur)->test(PagePublication::class, ['publication' => $this->atelier])
        ->assertSeeText('Votre explication')->assertSeeText('Envoyer mon explication')
        ->set('explanation', 'Le paiement couvre seulement la salle.')->call('explain')->assertHasNoErrors()->assertSeeText('Explication envoyée');

    $p = $this->atelier->fresh();
    expect($p->author_explanation)->toBe('Le paiement couvre seulement la salle.')->and($p->explained_at)->not->toBeNull();

    Livewire::actingAs($this->auteur)->test(PagePublication::class, ['publication' => $this->atelier])
        ->assertSet('explanation', 'Le paiement couvre seulement la salle.')->assertSeeText('Mettre à jour mon explication')
        ->set('explanation', 'Précision : la salle est gratuite pour les inscrits.')->call('explain');

    expect($this->atelier->fresh()->author_explanation)->toBe('Précision : la salle est gratuite pour les inscrits.');
});

it('exige une explication non vide d’au plus 500 caractères', function () {
    moderation()->hide($this->mod, $this->atelier, 'Motif');

    foreach (['', '   '] as $vide) {
        Livewire::actingAs($this->auteur)->test(PagePublication::class, ['publication' => $this->atelier])->set('explanation', $vide)->call('explain')->assertHasErrors(['explanation']);
    }
    Livewire::actingAs($this->auteur)->test(PagePublication::class, ['publication' => $this->atelier])->set('explanation', str_repeat('é', 501))->call('explain')->assertHasErrors(['explanation']);
    Livewire::actingAs($this->auteur)->test(PagePublication::class, ['publication' => $this->atelier])->set('explanation', str_repeat('é', 500))->call('explain')->assertHasNoErrors();
});

it('réserve l’explication à l’auteur, et seulement tant que la publication est masquée', function () {
    expect(fn () => moderation()->explain($this->auteur, $this->atelier, 'Pas masquée'))->toThrow(AuthorizationException::class);

    moderation()->hide($this->mod, $this->atelier, 'Motif');

    foreach ([$this->p1, $this->mod, $this->admin] as $autre) {
        expect(fn () => moderation()->explain($autre, $this->atelier, 'Je m’explique à sa place'))->toThrow(AuthorizationException::class);
    }

    expect($this->atelier->fresh()->author_explanation)->toBeNull();
});

it('montre l’explication à la modération dans l’onglet des publications masquées', function () {
    moderation()->hide($this->mod, $this->atelier, 'Motif');
    moderation()->explain($this->auteur, $this->atelier->fresh(), 'Le paiement couvre seulement la salle.');

    Livewire::actingAs($this->mod)->test(FileModeration::class)->call('setTab', 'masquees')
        ->assertSeeText('EXPLICATION DE L’AUTEUR')->assertSeeText('Le paiement couvre seulement la salle.');

    // Rétablie, l'explication disparaît avec le masquage.
    moderation()->restore($this->mod, $this->atelier->fresh());
    expect($this->atelier->fresh()->author_explanation)->toBeNull()->and($this->atelier->fresh()->explained_at)->toBeNull();
});

it('un nouveau masquage repart d’une page blanche : l’ancienne explication n’est pas reprise', function () {
    moderation()->hide($this->mod, $this->atelier, 'Premier motif');
    moderation()->explain($this->auteur, $this->atelier->fresh(), 'Ancienne explication');
    moderation()->restore($this->mod, $this->atelier->fresh());
    moderation()->hide($this->mod, $this->atelier->fresh(), 'Second motif');

    expect($this->atelier->fresh()->author_explanation)->toBeNull();
});

// ─── À faire maintenant ──────────────────────────────────────────────────────

it('dit à l’auteur qu’une publication a été masquée et que la modération attend son explication, jusqu’à ce qu’il s’explique', function () {
    moderation()->hide($this->mod, $this->atelier, 'Motif');

    Livewire::actingAs($this->auteur)->test(Espace::class)
        ->assertSeeText('Une publication a été masquée')->assertSeeText('La modération attend votre explication')->assertSeeText('Expliquer')->assertSeeText('Masquée')
        ->assertSee(route('publications.show', $this->atelier), false);

    moderation()->explain($this->auteur, $this->atelier->fresh(), 'Voilà.');

    Livewire::actingAs($this->auteur)->test(Espace::class)->assertDontSeeText('La modération attend votre explication');
});

it('ne propose plus d’accepter des réponses sur une publication masquée', function () {
    $besoin = publication($this->auteur, data: ['body' => 'Un besoin avec une réponse']);
    geste($this->p1, $besoin);

    Livewire::actingAs($this->auteur)->test(Espace::class)->assertSeeText('1 réponse à traiter');

    moderation()->hide($this->mod, $besoin, 'Motif');

    Livewire::actingAs($this->auteur)->test(Espace::class)->assertDontSeeText('réponse à traiter');
});

it('dit à la modération combien de publications attendent un examen, et pas aux autres', function () {
    Livewire::actingAs($this->mod)->test(Espace::class)->assertSeeText('1 publication signalée')->assertSeeText('Examiner')->assertSee(route('moderation'), false);
    Livewire::actingAs($this->admin)->test(Espace::class)->assertSeeText('1 publication signalée');
    Livewire::actingAs($this->p1)->test(Espace::class)->assertDontSeeText('publication signalée');

    moderation()->dismiss($this->mod, $this->atelier);
    Livewire::actingAs($this->mod)->test(Espace::class)->assertDontSeeText('publication signalée');
});

it('annonce la section Modération dans Espace aux seuls modérateurs et super administrateur', function () {
    Livewire::actingAs($this->mod)->test(Espace::class)->assertSee(route('moderation'), false)->assertSeeText('Ouvrir la file de modération');
    Livewire::actingAs($this->p1)->test(Espace::class)->assertDontSeeText('Ouvrir la file de modération');
});

// ─── Parcours complet ────────────────────────────────────────────────────────

it('parcours : un signalement va jusqu’au masquage, l’auteur s’explique, la modération rétablit', function () {
    $autre = publication($this->auteur, data: ['body' => 'Une publication qui sera signalée à tort']);
    Livewire::actingAs($this->p1)->test(SignalerPublication::class, ['publication' => $autre])->set('category', 'misinformation')->set('details', 'Je ne suis pas d’accord.')->call('send')->assertHasNoErrors();

    // 1. La modération la voit dans la file et la masque avec un motif.
    Livewire::actingAs($this->mod)->test(FileModeration::class)->assertSeeText('Une publication qui sera signalée à tort')
        ->set('reasons.'.$autre->id, 'Information non vérifiée.')->call('hide', $autre->id);

    // 2. L'auteur est prévenu, voit le motif et s'explique ; les autres ne la voient plus.
    Livewire::actingAs($this->auteur)->test(PageNotifications::class)->assertSeeText('a masqué votre publication');
    $this->actingAs($this->p2)->get(route('publications.show', $autre))->assertNotFound();
    Livewire::actingAs($this->auteur)->test(PagePublication::class, ['publication' => $autre])
        ->assertSeeText('Information non vérifiée.')->set('explanation', 'La source est dans mon texte.')->call('explain')->assertHasNoErrors();

    // 3. La modération lit l'explication et rétablit : la publication revient dans le Fil.
    Livewire::actingAs($this->mod)->test(FileModeration::class)->call('setTab', 'masquees')->assertSeeText('La source est dans mon texte.')
        ->set('reasons.'.$autre->id, 'Explication satisfaisante.')->call('restore', $autre->id);
    Livewire::actingAs($this->p2)->test(Fil::class)->assertSeeText('Une publication qui sera signalée à tort');
    Livewire::actingAs($this->auteur)->test(PageNotifications::class)->assertSeeText('a rétabli votre publication');

    // 4. Chaque acte est au journal, dans l'ordre.
    expect(AdminAction::query()->whereIn('action', ['publication.hidden', 'publication.restored'])->orderBy('created_at')->pluck('action')->all())->toBe(['publication.hidden', 'publication.restored']);
});
