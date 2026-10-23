<?php

namespace App\Services;

use App\Models\User;
use App\Models\Zumra;
use App\Models\ZumraPost;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * La Discussion d'une ZUMRA (D-021, D-112) : un fil simple, réservé aux membres, en texte brut de 1 000 caractères au plus.
 * Un membre supprime ses propres messages ; le fondateur et les responsables peuvent supprimer ceux des autres.
 */
class ZumraPosts
{
    /**
     * @throws ValidationException
     */
    public function post(User $user, Zumra $zumra, ?string $text): ZumraPost
    {
        Gate::forUser($user)->authorize('discuss', $zumra);

        $body = Normalizer::multiline((string) $text);

        if ($body === '') {
            throw ValidationException::withMessages(['postBody' => 'Écrivez votre message.']);
        }

        if (mb_strlen($body) > ZumraPost::BODY_MAX) {
            throw ValidationException::withMessages(['postBody' => 'Un message ne peut pas dépasser '.ZumraPost::BODY_MAX.' caractères.']);
        }

        $this->throttle($user, $zumra);

        $post = new ZumraPost(['body' => $body]);
        $post->zumra()->associate($zumra);
        $post->author()->associate($user);
        $post->save();

        return $post;
    }

    /** Supprime un message : son auteur, le fondateur ou un responsable de la ZUMRA. */
    public function delete(User $actor, ZumraPost $post): void
    {
        $zumra = $post->zumra;

        Gate::forUser($actor)->authorize('discuss', $zumra);

        if ($post->user_id !== $actor->getKey() && ! $zumra->roleOf($actor)?->manages()) {
            abort(403);
        }

        $post->delete();
    }

    /** Limites de débit contre le spam (D-117) : par minute et par jour, par personne. */
    private function throttle(User $user, Zumra $zumra): void
    {
        // Outil avancé d'une ZUMRA engagée (D-166) : plafond du jour relevé.
        $day = (int) config('gamad.limits.zumra_posts_per_day') * (app(Contributions::class)->isEngaged($zumra) ? (int) config('gamad.engaged_limit_multiplier') : 1);

        $limits = [
            [config('gamad.limits.zumra_posts_per_minute'), 60, 'Vous écrivez trop vite. Réessayez dans un instant.'],
            [$day, 86400, 'Vous avez atteint la limite de messages pour aujourd’hui.'],
        ];

        foreach ($limits as $index => [$max, , $message]) {
            if (RateLimiter::tooManyAttempts("zumra-post:{$user->getKey()}:$index", $max)) {
                throw ValidationException::withMessages(['postBody' => $message]);
            }
        }

        foreach ($limits as $index => [, $window]) {
            RateLimiter::hit("zumra-post:{$user->getKey()}:$index", $window);
        }
    }
}
