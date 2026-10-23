<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** D-184 : une prochaine action privée, proposée puis confirmée par l'autre participant. */
class ConversationActions
{
    public function __construct(private readonly Conversations $conversations) {}

    public function propose(User $actor, Conversation $conversation, string $text, ?string $date, int $revision): void
    {
        $this->change($actor, $conversation, $revision, function (Conversation $locked) use ($actor, $text, $date) {
            $text = Normalizer::multiline($text);
            $date = filled($date) ? $date : null;
            Validator::make(['actionText' => $text, 'actionDate' => $date], [
                'actionText' => ['required', 'string', 'max:'.Conversation::ACTION_MAX],
                'actionDate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.now()->timezone('Africa/Abidjan')->toDateString()],
            ], [], ['actionText' => 'prochaine action', 'actionDate' => 'date proposée'])->validate();

            $locked->forceFill(['next_action' => $text, 'next_action_date' => $date, 'next_action_by' => $actor->getKey(), 'next_action_confirmed_at' => null])->save();
            $this->conversations->send($actor, $locked, 'Prochaine action proposée : '.$text.($date ? "\nDate proposée : ".$date : ''));
        });
    }

    public function confirm(User $actor, Conversation $conversation, int $revision): void
    {
        $this->change($actor, $conversation, $revision, function (Conversation $locked) use ($actor) {
            if (! $locked->next_action || $locked->next_action_by === $actor->getKey() || $locked->next_action_confirmed_at) {
                throw ValidationException::withMessages(['action' => 'Seule l’autre personne peut confirmer une proposition en attente.']);
            }

            $locked->forceFill(['next_action_confirmed_at' => now()])->save();
            // Ne pas recopier le texte de l'autre personne : il doit pouvoir disparaître avec son compte.
            $this->conversations->send($actor, $locked, 'Prochaine action confirmée.');
        });
    }

    public function withdraw(User $actor, Conversation $conversation, int $revision): void
    {
        $this->change($actor, $conversation, $revision, function (Conversation $locked) use ($actor) {
            if (! $locked->next_action) {
                throw ValidationException::withMessages(['action' => 'Il n’y a plus d’action à retirer.']);
            }

            $locked->forceFill(['next_action' => null, 'next_action_date' => null, 'next_action_by' => null, 'next_action_confirmed_at' => null])->save();
            $this->conversations->send($actor, $locked, 'Prochaine action retirée. Convenez ensemble de la suite dans cet échange.');
        });
    }

    private function change(User $actor, Conversation $conversation, int $revision, callable $change): void
    {
        DB::transaction(function () use ($actor, $conversation, $revision, $change) {
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->getKey());
            Gate::forUser($actor)->authorize('send', $locked);

            if ($locked->next_action_revision !== $revision) {
                throw ValidationException::withMessages(['action' => 'L’action a changé depuis votre lecture. Relisez-la ; pour une nouvelle proposition, fermez puis rouvrez le formulaire.']);
            }

            $change($locked);
            $locked->forceFill(['next_action_revision' => $revision + 1])->save();
        });
    }
}
