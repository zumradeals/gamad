<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Models\Conversation;
use App\Models\Gesture;
use App\Models\Message;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Ouvrir une conversation privée (un geste accepté), y écrire et la marquer lue. Toute règle est tenue ici, côté serveur
 * (ConversationPolicy) : seuls les deux participants lisent et écrivent.
 */
class Conversations
{
    public function __construct(private readonly Notifier $notifier) {}

    /** Ouvre la conversation d'un geste accepté (une seule par geste) et prévient la personne acceptée. */
    public function open(Gesture $gesture): Conversation
    {
        $publication = $gesture->publication;

        $conversation = Conversation::query()->where('gesture_id', $gesture->getKey())->first();

        if (! $conversation) {
            $conversation = new Conversation(['owner_id' => $publication->author_id, 'helper_id' => $gesture->user_id]);
            $conversation->gesture()->associate($gesture);
            $conversation->publication()->associate($publication);
            $conversation->save();
        }

        $this->notifier->push($gesture->author, NotificationKind::GestureAccepted, $publication->author, $publication, $conversation);

        return $conversation;
    }

    /**
     * Écrit un message. Texte brut, 1 000 caractères au plus, non vide ; refusé si l'autre personne a supprimé son compte.
     *
     * @throws ValidationException
     */
    public function send(User $sender, Conversation $conversation, ?string $text): Message
    {
        Gate::forUser($sender)->authorize('send', $conversation);

        $body = Normalizer::multiline((string) $text);

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Écrivez votre message.']);
        }

        if (mb_strlen($body) > Conversation::MESSAGE_MAX) {
            throw ValidationException::withMessages(['body' => 'Un message ne peut pas dépasser '.Conversation::MESSAGE_MAX.' caractères.']);
        }

        $this->throttle($sender);

        return DB::transaction(function () use ($sender, $conversation, $body) {
            $locked = Conversation::query()->whereKey($conversation->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($sender)->authorize('send', $locked);

            $message = new Message(['body' => $body]);
            $message->conversation()->associate($locked);
            $message->sender()->associate($sender);
            $message->save();

            // Ce qu'on écrit, on l'a lu.
            $locked->forceFill([$locked->readColumn($sender) => $message->created_at])->save();

            $this->notifier->push($locked->otherPerson($sender), NotificationKind::MessageReceived, $sender, $locked->publication, $locked);

            return $message;
        });
    }

    /** La personne vient de lire la conversation : plus rien n'est « non lu » pour elle. */
    public function markRead(User $reader, Conversation $conversation): void
    {
        Gate::forUser($reader)->authorize('view', $conversation);

        $column = $conversation->readColumn($reader);
        $latest = Message::query()->where('conversation_id', $conversation->getKey())->max('created_at');

        // « Lu jusqu'au dernier message vu » : un message qui arrive pendant la lecture reste non lu.
        if ($latest !== null && ($conversation->{$column} === null || $conversation->{$column}->lt(Carbon::parse($latest)))) {
            Conversation::query()->whereKey($conversation->getKey())->update([$column => $latest]);
            $conversation->{$column} = Carbon::parse($latest);
        }

        $this->notifier->readConversation($reader, $conversation);
    }

    /** Limites de débit contre le spam (D-086) : par minute et par jour, par personne. */
    private function throttle(User $user): void
    {
        $limits = [
            [config('gamad.limits.messages_per_minute'), 60, 'Vous écrivez trop vite. Réessayez dans un instant.'],
            [config('gamad.limits.messages_per_day'), 86400, 'Vous avez atteint la limite de messages pour aujourd’hui.'],
        ];

        foreach ($limits as $index => [$max, , $message]) {
            if (RateLimiter::tooManyAttempts("message:{$user->getKey()}:$index", $max)) {
                throw ValidationException::withMessages(['body' => $message]);
            }
        }

        foreach ($limits as $index => [, $window]) {
            RateLimiter::hit("message:{$user->getKey()}:$index", $window);
        }
    }
}
