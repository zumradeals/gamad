<?php

use App\Enums\PublicationType;
use App\Livewire\Fil;
use App\Livewire\ModerationCommentaires;
use App\Livewire\PublicationDiscussion;
use App\Models\Conversation;
use App\Models\Gesture;
use App\Models\PublicationComment;
use App\Models\PublicationCommentReport;
use App\Models\User;
use App\Services\Moderation;
use App\Services\People;
use App\Services\PublicationComments;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->auteur = person('Awa Traoré');
    $this->lecteur = person('Yao Kouassi');
    $this->publication = publication($this->auteur, PublicationType::Share, ['body' => 'Une expérience de couture.']);
});

function commentaires(): PublicationComments
{
    return app(PublicationComments::class);
}

it('affiche le lien social et la discussion sur les publications personnelles', function () {
    $this->actingAs($this->lecteur)->get(route('publications.show', $this->publication))
        ->assertOk()->assertSeeText('Discussion publique')->assertSeeText('Participer à la discussion');
    Livewire::actingAs($this->lecteur)->test(Fil::class)->assertSeeText('Commenter');

    $c = commentaires()->create($this->lecteur, $this->publication, 'Un conseil utile.');
    $this->get(route('publications.show', $this->publication))->assertSeeText($c->body);
    expect(PublicationComment::count())->toBe(1);
});

it('publie une réponse à un seul niveau sans créer un geste ou un échange privé', function () {
    $c = commentaires()->create($this->lecteur, $this->publication, 'Bravo Awa.');
    Livewire::actingAs($this->auteur)->test(PublicationDiscussion::class, ['publication' => $this->publication])
        ->call('reply', $c->id)->set('body', 'Merci Yao !')->call('send')->assertHasNoErrors()
        ->assertSeeText('Merci Yao !');

    $reply = PublicationComment::where('parent_id', $c->id)->firstOrFail();
    expect($reply->author_id)->toBe($this->auteur->id)
        ->and($reply->parent_id)->toBe($c->id)
        ->and(Gesture::count())->toBe(0)
        ->and(Conversation::count())->toBe(0);

    expect(fn () => commentaires()->create($this->lecteur, $this->publication, 'Profondeur interdite.', $reply->id))
        ->toThrow(HttpException::class);
});

it('autorise modification puis suppression uniquement par son auteur', function () {
    $comment = commentaires()->create($this->lecteur, $this->publication, 'Version initiale.');
    commentaires()->edit($this->lecteur, $comment, 'Texte corrigé.');
    expect($comment->fresh()->body)->toBe('Texte corrigé.');

    expect(fn () => commentaires()->edit($this->auteur, $comment, 'Volé !'))->toThrow(AuthorizationException::class);
    expect(fn () => commentaires()->delete($this->auteur, $comment))->toThrow(AuthorizationException::class);

    commentaires()->delete($this->lecteur, $comment);
    expect($comment->fresh()->body)->toBeNull()->and($comment->fresh()->deleted_at)->not->toBeNull();
    $this->actingAs($this->auteur)->get(route('publications.show', $this->publication))
        ->assertSeeText('Commentaire supprimé par son auteur.')->assertDontSeeText('Texte corrigé.');
});

it('vérifie les limites, normalise le texte et échappe le HTML', function () {
    expect(fn () => commentaires()->create($this->lecteur, $this->publication, '   '))->toThrow(ValidationException::class);
    expect(fn () => commentaires()->create($this->lecteur, $this->publication, str_repeat('a', 501)))->toThrow(ValidationException::class);
    $comment = commentaires()->create($this->lecteur, $this->publication, '<script>alert(1)</script>');
    $html = $this->actingAs($this->auteur)->get(route('publications.show', $this->publication))->getContent();
    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')->not->toContain('<script>alert(1)</script>')
        ->and($comment->body)->toBe('<script>alert(1)</script>');
});

it('protège les commentaires des publications réservées et masquées', function () {
    $hidden = publication($this->auteur, PublicationType::Knowledge, ['body' => 'Réservé', 'visibility' => 'members']);
    commentaires()->create($this->lecteur, $hidden, 'Secret des membres');
    auth()->logout();
    $this->get(route('publications.show', $hidden))->assertNotFound()->assertDontSeeText('Secret des membres');
    expect(fn () => commentaires()->create(User::factory()->unverified()->create(), $hidden, 'Interdit'))->toThrow(AuthorizationException::class);

    app(Moderation::class)->hide(User::factory()->moderator()->create(), $this->publication, 'À examiner');
    expect(fn () => commentaires()->create($this->lecteur, $this->publication, 'Après masquage'))->toThrow(AuthorizationException::class);
});

it('respecte les blocages et ne commente pas les publications collectives', function () {
    app(People::class)->block($this->auteur, $this->lecteur, true);
    expect(fn () => commentaires()->create($this->lecteur, $this->publication, 'Interdit'))->toThrow(AuthorizationException::class);
    $org = zumra($this->auteur);
    $collective = publication($this->auteur, PublicationType::Knowledge);
    $collective->forceFill(['zumra_id' => $org->id])->save();
    expect(fn () => commentaires()->create(person('Autre'), $collective, 'Non'))->toThrow(HttpException::class);
});

it('enregistre un signalement privé, refuse le doublon et son propre commentaire', function () {
    $comment = commentaires()->create($this->lecteur, $this->publication, 'À examiner.');
    commentaires()->report($this->auteur, $comment, 'spam', 'Hors sujet');
    expect(PublicationCommentReport::count())->toBe(1);
    expect(fn () => commentaires()->report($this->auteur, $comment, 'spam'))->toThrow(ValidationException::class);
    expect(fn () => commentaires()->report($this->lecteur, $comment, 'spam'))->toThrow(AuthorizationException::class);
    $html = $this->actingAs($this->lecteur)->get(route('publications.show', $this->publication))->getContent();
    expect($html)->not->toContain('Hors sujet')->not->toContain('spam');
});

it('permet de signaler directement dans la discussion', function () {
    $c = commentaires()->create($this->lecteur, $this->publication, 'Un commentaire.');
    Livewire::actingAs($this->auteur)->test(PublicationDiscussion::class, ['publication' => $this->publication])
        ->call('signaler', $c->id)->set('reportCategory', 'spam')->call('sendReport')
        ->assertHasNoErrors()->assertSeeText('Signalement transmis à la modération.');
    expect(PublicationCommentReport::where('comment_id', $c->id)->count())->toBe(1);
});

it('le modérateur masque, journalise et peut rétablir le commentaire', function () {
    $comment = commentaires()->create($this->lecteur, $this->publication, 'Publication à contrôler.');
    commentaires()->report($this->auteur, $comment, 'spam');
    $moderator = User::factory()->moderator()->create();
    $this->actingAs($moderator)->get(route('moderation.commentaires'))->assertOk()->assertSeeText('Publication à contrôler.');

    commentaires()->hide($moderator, $comment, 'Contenu incompatible avec la charte');
    expect($comment->fresh()->hidden_at)->not->toBeNull();
    expect(PublicationCommentReport::where('comment_id', $comment->id)->firstOrFail()->resolution)->toBe('hidden');
    expect(DB::table('admin_actions')->where('action', 'comment.hidden')->exists())->toBeTrue();

    $this->actingAs($this->auteur)->get(route('publications.show', $this->publication))
        ->assertSeeText('Commentaire masqué par la modération.')->assertDontSeeText('Publication à contrôler.');
    commentaires()->explain($this->lecteur, $comment->fresh(), 'Je demande une relecture.');
    Livewire::actingAs($moderator)->test(ModerationCommentaires::class)
        ->call('setTab', 'masques')->assertSeeText('Je demande une relecture.');
    commentaires()->restore($moderator, $comment, 'Explication recevable');
    expect($comment->fresh()->hidden_at)->toBeNull();
    expect(DB::table('admin_actions')->where('action', 'comment.restored')->exists())->toBeTrue();
});

it('refuse la modération par un membre simple ou par le modérateur auteur', function () {
    $comment = commentaires()->create($this->lecteur, $this->publication, 'Mon commentaire');
    expect(fn () => commentaires()->hide($this->auteur, $comment, 'Une raison'))->toThrow(AuthorizationException::class);
    $ownModerator = User::factory()->moderator()->create();
    $hisComment = commentaires()->create($ownModerator, $this->publication, 'Mon commentaire de modérateur');
    expect(fn () => commentaires()->hide($ownModerator, $hisComment, 'Une raison'))->toThrow(ValidationException::class);
    $this->actingAs($this->auteur)->get(route('moderation.commentaires'))->assertForbidden();
});

it('classe un signalement sans masquer et journalise le choix', function () {
    $c = commentaires()->create($this->lecteur, $this->publication, 'Contenu normal.');
    commentaires()->report($this->auteur, $c, 'other', 'À vérifier.');
    $moderator = User::factory()->moderator()->create();
    commentaires()->dismiss($moderator, $c, 'Aucune violation');
    expect($c->fresh()->hidden_at)->toBeNull()
        ->and(PublicationCommentReport::firstOrFail()->resolution)->toBe('dismissed')
        ->and(DB::table('admin_actions')->where('action', 'comment.report.dismissed')->exists())->toBeTrue();
});
