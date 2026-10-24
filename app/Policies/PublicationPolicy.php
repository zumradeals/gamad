<?php

namespace App\Policies;

use App\Enums\GestureState;
use App\Enums\NeedState;
use App\Models\Gesture;
use App\Models\Publication;
use App\Models\User;
use App\Services\People;
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
        // Chacun voit les siennes ; celles de niveau ZUMRA ne se lisent que par un membre de la ZUMRA (D-112).
        if ($viewer && $viewer->getKey() === $publication->author_id && $publication->visibility !== Publication::ZUMRA) {
            return Response::allow();
        }

        if ($viewer && ! $viewer->can('moderate') && People::blocked($viewer, $publication->author)) {
            return Response::denyAsNotFound();
        }

        // Masquée (D-089) : pour tout autre que ceux qui la gèrent et la modération, elle n'existe pas.
        if ($publication->isHidden()) {
            return $viewer && ($viewer->can('moderate') || $publication->isManagedBy($viewer)) ? Response::allow() : Response::denyAsNotFound();
        }

        if (! $publication->author->isActive()) {
            return Response::denyAsNotFound();
        }

        if ($publication->isPublic()) {
            return Response::allow();
        }

        if (! $viewer?->hasVerifiedEmail()) {
            return Response::denyAsNotFound();
        }

        if ($publication->visibility === Publication::ZUMRA) {
            return $publication->zumra_id !== null && $viewer->zumraMemberships()->where('zumra_id', $publication->zumra_id)->exists()
                ? Response::allow()
                : Response::denyAsNotFound();
        }

        return Response::allow();
    }

    /** Publier : une personne connectée dont l'adresse e-mail est vérifiée. */
    public function create(User $user): Response
    {
        return $user->hasVerifiedEmail() ? Response::allow() : Response::deny('Vérifiez votre adresse e-mail pour publier.');
    }

    /**
     * Gérer une publication (voir les gestes reçus, décider, la résoudre, remercier) : son auteur, ou, pour une publication faite
     * au nom d'une ZUMRA, le fondateur et les responsables de cette ZUMRA (D-116).
     */
    public function manage(User $user, Publication $publication): bool
    {
        return $publication->isManagedBy($user);
    }

    /**
     * Soutenir une avancée de projet (D-139) : une personne vérifiée qui voit la publication, sans la gérer, tant qu'elle n'est pas
     * masquée ; un seul soutien par personne, que l'on peut retirer.
     */
    public function support(User $user, Publication $publication): Response
    {
        if (! $publication->type->canBeSupported()) {
            return Response::denyAsNotFound();
        }

        if (! $user->hasVerifiedEmail()) {
            return Response::deny('Vérifiez votre adresse e-mail pour soutenir.');
        }

        if (! $this->view($user, $publication)->allowed() || $publication->isHidden()) {
            return Response::denyAsNotFound();
        }

        if ($publication->isManagedBy($user)) {
            return Response::deny('Vous ne soutenez pas votre propre avancée.');
        }

        return Response::allow();
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

        if ($publication->isManagedBy($user)) {
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

    /** Remercier (D-084) : l'auteur d'un besoin résolu qui a au moins un geste accepté, une seule fois. */
    public function thank(User $user, Publication $publication): Response
    {
        if (! $this->manage($user, $publication)) {
            return Response::deny('Seul l’auteur du besoin peut remercier.');
        }

        if (! $publication->isNeed() || $publication->need_state !== NeedState::Resolved) {
            return Response::deny('On remercie une fois le besoin résolu.');
        }

        if ($publication->gratitude()->exists()) {
            return Response::deny('Ce besoin a déjà un remerciement.');
        }

        return Response::allow();
    }

    /** Signaler (écran 16) : une personne vérifiée qui voit la publication, jamais la sienne. */
    public function report(User $user, Publication $publication): Response
    {
        if (! $user->hasVerifiedEmail()) {
            return Response::deny('Vérifiez votre adresse e-mail pour signaler.');
        }

        if ($user->getKey() === $publication->author_id) {
            return Response::deny('Vous ne pouvez pas signaler votre propre publication.');
        }

        return $this->view($user, $publication)->allowed() ? Response::allow() : Response::denyAsNotFound();
    }

    /** Écrire une explication : l'auteur seul, tant que sa publication est masquée (D-089). */
    public function explain(User $user, Publication $publication): Response
    {
        if (! $this->manage($user, $publication)) {
            return Response::denyAsNotFound();
        }

        return $publication->isHidden() ? Response::allow() : Response::deny('Cette publication n’est pas masquée.');
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

        if ($publication->isHidden()) {
            return Response::deny('Cette publication est masquée.');
        }

        return $publication->need_state?->isActive() ? Response::allow() : Response::deny('Ce besoin est déjà clos.');
    }
}
