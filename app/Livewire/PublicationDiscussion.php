<?php

namespace App\Livewire;

use App\Models\Publication;
use App\Models\PublicationComment;
use App\Services\PublicationComments;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Discussion publique, distincte des propositions d'aide privées (D-193). */
class PublicationDiscussion extends Component
{
    #[Locked]
    public string $publicationId;

    public string $body = '';

    public ?string $replyTo = null;

    public ?string $editingId = null;

    public string $editBody = '';

    public ?string $reportId = null;

    public string $reportCategory = '';

    public string $reportDetails = '';

    public ?string $explainId = null;

    public string $explanation = '';

    public int $limit = 20;

    public string $notice = '';

    public function mount(Publication $publication): void
    {
        $this->publicationId = $publication->id;
    }

    public function reply(string $id): void
    {
        $parent = $this->comment($id);
        abort_if($parent->parent_id || ! $parent->isVisible(), 404);
        $this->replyTo = $id;
        $this->editingId = null;
        $this->resetErrorBag();
    }

    public function cancelReply(): void
    {
        $this->replyTo = null;
    }

    public function send(PublicationComments $comments): void
    {
        $post = $this->publication();
        $comments->create(Auth::user(), $post, $this->body, $this->replyTo);
        $this->reset('body', 'replyTo');
        $this->notice = 'Commentaire publié.';
        $this->dispatch('comment-changed');
    }

    public function edit(string $id): void
    {
        $comment = $this->comment($id);
        abort_unless(Auth::id() === $comment->author_id && $comment->isVisible(), 403);
        $this->editingId = $id;
        $this->editBody = (string) $comment->body;
        $this->replyTo = null;
    }

    public function saveEdit(PublicationComments $comments): void
    {
        abort_unless($this->editingId && Str::isUuid($this->editingId), 404);
        $comments->edit(Auth::user(), $this->comment($this->editingId), $this->editBody);
        $this->reset('editingId', 'editBody');
        $this->notice = 'Commentaire modifié.';
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editBody');
    }

    public function delete(string $id, PublicationComments $comments): void
    {
        $comments->delete(Auth::user(), $this->comment($id));
        $this->notice = 'Commentaire supprimé.';
        $this->dispatch('comment-changed');
    }

    public function signaler(string $id): void
    {
        $comment = $this->comment($id);
        abort_if(! $comment->isVisible() || $comment->author_id === Auth::id(), 403);
        $this->reportId = $id;
        $this->reset('reportCategory', 'reportDetails');
    }

    public function sendReport(PublicationComments $comments): void
    {
        abort_unless($this->reportId && Str::isUuid($this->reportId), 404);
        $comments->report(Auth::user(), $this->comment($this->reportId), $this->reportCategory, $this->reportDetails);
        $this->reset('reportId', 'reportCategory', 'reportDetails');
        $this->notice = 'Signalement transmis à la modération.';
    }

    public function cancelReport(): void
    {
        $this->reset('reportId', 'reportCategory', 'reportDetails');
    }

    public function explain(string $id): void
    {
        $comment = $this->comment($id);
        abort_unless($comment->hidden_at && $comment->author_id === Auth::id(), 403);
        $this->explainId = $id;
        $this->explanation = (string) $comment->author_explanation;
    }

    public function sendExplanation(PublicationComments $comments): void
    {
        abort_unless($this->explainId && Str::isUuid($this->explainId), 404);
        $comments->explain(Auth::user(), $this->comment($this->explainId), $this->explanation);
        $this->reset('explainId', 'explanation');
        $this->notice = 'Explication envoyée à la modération.';
    }

    public function more(): void
    {
        $this->limit = min($this->limit + 20, 100);
    }

    public function render()
    {
        $publication = $this->publication();
        $all = PublicationComment::query()->where('publication_id', $publication->id)->whereNull('parent_id')
            ->whereHas('author', fn ($q) => $q->active())
            ->with(['author', 'replies' => fn ($q) => $q->whereHas('author', fn ($u) => $u->active())->limit(50), 'replies.author'])
            ->orderBy('created_at')->orderBy('id')->limit($this->limit + 1)->get();

        return view('livewire.publication-discussion', [
            'publication' => $publication,
            'comments' => $all->take($this->limit),
            'hasMore' => $all->count() > $this->limit && $this->limit < 100,
            'viewer' => Auth::user(),
        ]);
    }

    private function publication(): Publication
    {
        $publication = Publication::query()->with('author')->findOrFail($this->publicationId);
        Gate::authorize('view', $publication);
        abort_if($publication->isHidden() || $publication->zumra_id || $publication->project_id, 404);

        return $publication;
    }

    private function comment(string $id): PublicationComment
    {
        abort_unless(Str::isUuid($id), 404);
        $this->publication();

        return PublicationComment::query()->with(['publication.author', 'author', 'parent.author'])
            ->where('publication_id', $this->publicationId)->findOrFail($id);
    }
}
