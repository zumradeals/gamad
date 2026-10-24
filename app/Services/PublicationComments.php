<?php

namespace App\Services;

use App\Enums\ReportCategory;
use App\Models\Publication;
use App\Models\PublicationComment;
use App\Models\PublicationCommentReport;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** D-193 : conversations publiques. Aucun geste, relation ni échange privé n'est créé ici. */
class PublicationComments
{
    public function create(User $actor, Publication $publication, ?string $body, ?string $parentId = null): PublicationComment
    {
        $this->checkAudience($actor, $publication);
        $body = $this->validBody($body);
        $parent = null;

        if ($parentId !== null) {
            $parent = PublicationComment::query()->where('publication_id', $publication->id)->findOrFail($parentId);
            abort_if($parent->parent_id || ! $parent->isVisible(), 404);
        }

        $key = 'gamad:comment:'.$actor->id;
        if (RateLimiter::tooManyAttempts($key, 30)) {
            throw ValidationException::withMessages(['body' => 'Vous commentez trop vite. Réessayez dans un moment.']);
        }

        $comment = new PublicationComment(['body' => $body]);
        $comment->publication()->associate($publication);
        $comment->author()->associate($actor);
        if ($parent) {
            $comment->parent()->associate($parent);
        }
        $comment->save();
        RateLimiter::hit($key, 3600);

        return $comment;
    }

    public function edit(User $actor, PublicationComment $comment, ?string $body): void
    {
        $this->checkAudience($actor, $comment->publication);
        $this->assertOwner($actor, $comment);
        abort_if($comment->deleted_at || $comment->hidden_at, 404);
        $comment->update(['body' => $this->validBody($body)]);
    }

    public function delete(User $actor, PublicationComment $comment): void
    {
        $this->checkAudience($actor, $comment->publication);
        $this->assertOwner($actor, $comment);
        abort_if($comment->deleted_at, 404);
        // Un emplacement reste lisible pour ne pas décontextualiser les réponses existantes.
        $comment->forceFill(['body' => null, 'deleted_at' => now()])->save();
    }

    public function report(User $actor, PublicationComment $comment, ?string $category, ?string $details = null): void
    {
        $this->checkAudience($actor, $comment->publication);
        abort_if(! $comment->isVisible() || ($comment->parent_id && ! $comment->parent?->isVisible()), 404);
        if ($comment->author_id === $actor->id) {
            throw new AuthorizationException('Vous ne pouvez pas signaler votre propre commentaire.');
        }

        $reason = ReportCategory::tryFrom((string) $category);
        if (! $reason) {
            throw ValidationException::withMessages(['reportCategory' => 'Choisissez un motif de signalement.']);
        }
        $details = Normalizer::multiline((string) $details);
        if (mb_strlen($details) > 280) {
            throw ValidationException::withMessages(['reportDetails' => 'La précision ne peut pas dépasser 280 caractères.']);
        }

        if (PublicationCommentReport::query()->where('comment_id', $comment->id)->where('reporter_id', $actor->id)->exists()) {
            throw ValidationException::withMessages(['reportCategory' => 'Vous avez déjà signalé ce commentaire.']);
        }

        try {
            $report = new PublicationCommentReport(['category' => $reason, 'details' => $details ?: null]);
            $report->comment()->associate($comment);
            $report->reporter()->associate($actor);
            $report->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['reportCategory' => 'Vous avez déjà signalé ce commentaire.']);
        }
    }

    public function hide(User $moderator, PublicationComment $comment, ?string $reason): void
    {
        Gate::forUser($moderator)->authorize('moderate');
        $reason = $this->validReason($reason, true);
        DB::transaction(function () use ($moderator, $comment, $reason) {
            $locked = PublicationComment::query()->lockForUpdate()->findOrFail($comment->id);
            $this->refuseOwn($moderator, $locked);
            abort_if($locked->hidden_at || $locked->deleted_at, 404);
            $locked->forceFill(['hidden_at' => now(), 'hidden_by' => $moderator->id, 'hidden_reason' => $reason])->save();
            PublicationCommentReport::query()->where('comment_id', $locked->id)->open()->update(['resolution' => 'hidden', 'resolved_by' => $moderator->id, 'resolved_at' => now()]);
            AdminJournal::record($moderator, 'comment.hidden', $locked, $reason, ['publication_id' => $locked->publication_id, 'author_id' => $locked->author_id]);
        });
    }

    public function dismiss(User $moderator, PublicationComment $comment, ?string $reason = null): void
    {
        Gate::forUser($moderator)->authorize('moderate');
        $reason = $this->validReason($reason, false);
        DB::transaction(function () use ($moderator, $comment, $reason) {
            $locked = PublicationComment::query()->lockForUpdate()->findOrFail($comment->id);
            $this->refuseOwn($moderator, $locked);
            $reports = PublicationCommentReport::query()->where('comment_id', $locked->id)->open();
            if (! $reports->exists()) {
                throw ValidationException::withMessages(['reason' => 'Aucun signalement en attente.']);
            }
            $reports->update(['resolution' => 'dismissed', 'resolved_by' => $moderator->id, 'resolved_at' => now()]);
            AdminJournal::record($moderator, 'comment.report.dismissed', $locked, $reason, ['publication_id' => $locked->publication_id]);
        });
    }

    public function restore(User $moderator, PublicationComment $comment, ?string $reason = null): void
    {
        Gate::forUser($moderator)->authorize('moderate');
        $reason = $this->validReason($reason, false);
        DB::transaction(function () use ($moderator, $comment, $reason) {
            $locked = PublicationComment::query()->lockForUpdate()->findOrFail($comment->id);
            $this->refuseOwn($moderator, $locked);
            abort_unless($locked->hidden_at && ! $locked->deleted_at, 404);
            $locked->forceFill(['hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null, 'author_explanation' => null, 'explained_at' => null])->save();
            AdminJournal::record($moderator, 'comment.restored', $locked, $reason, ['publication_id' => $locked->publication_id]);
        });
    }

    public function explain(User $actor, PublicationComment $comment, ?string $explanation): void
    {
        $this->assertOwner($actor, $comment);
        $explanation = $this->validReason($explanation, true);
        abort_unless($comment->hidden_at && ! $comment->deleted_at, 404);
        $comment->forceFill(['author_explanation' => $explanation, 'explained_at' => now()])->save();
    }

    private function checkAudience(User $actor, Publication $publication): void
    {
        if (! $actor->isActive()) {
            throw new AuthorizationException('Vérifiez votre compte pour commenter.');
        }
        Gate::forUser($actor)->authorize('view', $publication);
        abort_if($publication->isHidden() || $publication->zumra_id || $publication->project_id, 404);
    }

    private function assertOwner(User $actor, PublicationComment $comment): void
    {
        if ($actor->id !== $comment->author_id) {
            throw new AuthorizationException('Seul l’auteur peut modifier ce commentaire.');
        }
    }

    private function validBody(?string $body): string
    {
        $body = Normalizer::multiline((string) $body);
        if ($body === '' || mb_strlen($body) > PublicationComment::BODY_MAX) {
            throw ValidationException::withMessages(['body' => 'Écrivez un commentaire de 1 à 500 caractères.']);
        }

        return $body;
    }

    private function validReason(?string $reason, bool $required): string
    {
        $reason = Normalizer::multiline((string) $reason);
        if (($required && $reason === '') || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Indiquez un motif de 1 à 500 caractères.']);
        }

        return $reason;
    }

    private function refuseOwn(User $moderator, PublicationComment $comment): void
    {
        if ($moderator->id === $comment->author_id) {
            throw ValidationException::withMessages(['reason' => 'Un autre modérateur doit examiner votre commentaire.']);
        }
    }
}
