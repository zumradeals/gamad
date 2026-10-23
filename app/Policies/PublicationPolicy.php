<?php

namespace App\Policies;

use App\Enums\GestureState;
use App\Models\Gesture;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Qui peut voir, gérer et répondre à une publication. Toutes les règles vivent ici, côté serveur : l'affichage ne fait que
 * les refléter.
 */
class PublicationPolicy
{
    /**
     * Voir (D-085). Une publication qu'on n'a pas le droit de voir n'existe pas pour soi : 404, jamais 403. Même règle, côté
     * requêtes, que Publication::scopeVisibleTo().
     */
    public function view(?User $viewer, Publication $publication): Response
    {
        if ($viewer && $viewer->getKey() === $publication->author_id) {
            return Response::allow();
        }

        if (! $publication->author->hasVerifiedEmail()) {
            return Response::denyAsNotFound();
        }

        return $publication->isPublic() || $viewer?->hasVerifiedEmail()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** Publier : une personne connectée dont l'adresse e-mail est vérifiée. */
    public function create(User $user): Response
    {
        return $user->hasVerifiedEmail() ? Response::allow() : Response::deny('Vérifiez votre adresse e-mail pour publier.');
    }

    /** Gérer une publication (voir les gestes reçus, décider) : son auteur seul. */
    public function manage(User $user, Publication $publication): bool
    {
        return $user->getKey() === $publication->author_id;
    }

    /**
     * Envoyer un geste (D-082) : une personne vérifiée qui voit la publication, qui n'en est pas l'auteur, tant que la
     * publication accepte des gestes ; une seule fois, sauf après avoir retiré le sien.
     */
    public function gesture(User $user, Publication $publication): Response
    {
        if (! $user->hasVerifiedEmail()) {
            return Response::deny('Vérifiez votre adresse e-mail pour répondre.');
        }

        if ($user->getKey() === $publication->author_id) {
            return Response::deny('Votre propre publication n’accepte pas de geste.');
        }

        if (! $this->view($user, $publication)->allowed()) {
            return Response::denyAsNotFound();
        }

        if (! $publication->acceptsGestures()) {
            return Response::deny('Ce besoin n’accepte plus de proposition.');
        }

        $existing = Gesture::query()->where('publication_id', $publication->getKey())->where('user_id', $user->getKey())->first();

        if ($existing && $existing->state !== GestureState::Withdrawn) {
            return Response::deny('Vous avez déjà répondu à cette publication.');
        }

        return Response::allow();
    }

    /** Marquer un besoin « résolu » ou « fermé sans suite » : son auteur seul, tant qu'il est ouvert ou en cours (D-083). */
    public function changeNeedState(User $user, Publication $publication): Response
    {
        if (! $this->manage($user, $publication)) {
            return Response::deny('Seul l’auteur du besoin peut le modifier.');
        }

        if (! $publication->isNeed()) {
            return Response::deny('Seul un besoin a un état.');
        }

        return $publication->need_state?->isActive() ? Response::allow() : Response::deny('Ce besoin est déjà clos.');
    }
}
