<?php

namespace App\Events\Matching;

use App\Models\Publication;

/** Fait survenu dans GAMAD Social qui concerne le Matching. Voir App\Listeners\MatchingSubscriber. */
final class PublicationMatchingChanged
{
    /**
     * @param  array<string, mixed>  $before  Valeurs d'avant des seuls champs qui ont changé.
     */
    public function __construct(public Publication $publication, public bool $created = false, public array $before = []) {}

    /** Le changement peut-il avoir ajouté des correspondances possibles ? (rouverte, démasquée, conditions ou audience modifiées) */
    public function mayAddMatches(): bool
    {
        if ($this->created) {
            return true;
        }

        $wasInactive = array_key_exists('need_state', $this->before) && ! ($this->before['need_state']?->isActive() ?? true);

        return $wasInactive || array_key_exists('hidden_at', $this->before) || array_key_exists('matching', $this->before) || array_key_exists('visibility', $this->before);
    }
}
