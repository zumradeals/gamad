<?php

namespace App\Services;

use App\Enums\GestureState;
use App\Enums\JoinRequestState;
use App\Enums\NeedState;
use App\Models\Conversation;
use App\Models\Gesture;
use App\Models\Invitation;
use App\Models\Message;
use App\Models\Publication;
use App\Models\Report;
use App\Models\User;
use App\Models\Zumra;
use Illuminate\Support\Str;

/**
 * « À faire maintenant » (écran 14) : seulement ce qui attend vraiment une action de la personne. Chaque ligne disparaît dès
 * que l'action est faite ; rien n'est affiché « pour faire joli ». La modération y voit aussi les signalements à examiner ; l'auteur d'une publication masquée, l'explication attendue.
 */
class Todo
{
    /** Un besoin résolu depuis plus longtemps n'est plus proposé au remerciement dans cette liste (on peut toujours remercier depuis le besoin). */
    public const THANK_WITHIN_DAYS = 7;

    public const MAX_LINES = 5;

    /**
     * @return array<int, array{key: string, title: string, detail: string, action: string, url: string}>
     */
    public function for(User $user): array
    {
        return [
            ...$this->hiddenPublications($user),
            ...$this->reportsToReview($user),
            ...$this->zumraInvitations($user),
            ...$this->joinRequestsToDecide($user),
            ...$this->proposals($user),
            ...$this->unreadMessages($user),
            ...$this->thanks($user),
        ];
    }

    /** Une publication de la personne a été masquée et la modération attend son explication (écran 14). */
    private function hiddenPublications(User $user): array
    {
        return Publication::query()
            ->where('author_id', $user->getKey())
            ->whereNotNull('hidden_at')
            ->whereNull('explained_at')
            ->orderByDesc('hidden_at')->limit(self::MAX_LINES)->get()
            ->map(fn (Publication $publication) => [
                'key' => 'hidden-'.$publication->getKey(),
                'title' => 'Une publication a été masquée',
                'detail' => 'La modération attend votre explication',
                'action' => 'Expliquer',
                'url' => route('publications.show', $publication),
            ])->all();
    }

    /** Pour la modération : des publications signalées attendent un examen. */
    private function reportsToReview(User $user): array
    {
        if (! $user->can('moderate')) {
            return [];
        }

        $count = Report::query()->open()->distinct('publication_id')->count('publication_id');

        return $count === 0 ? [] : [[
            'key' => 'moderation',
            'title' => $count.($count > 1 ? ' publications signalées' : ' publication signalée'),
            'detail' => 'À examiner dans la file de modération',
            'action' => 'Examiner',
            'url' => route('moderation'),
        ]];
    }

    /** Une invitation à rejoindre une ZUMRA attend la réponse de la personne invitée. */
    private function zumraInvitations(User $user): array
    {
        return Invitation::query()->open()->where('user_id', $user->getKey())->with(['zumra', 'inviter'])
            ->orderByDesc('created_at')->limit(self::MAX_LINES)->get()
            ->map(fn (Invitation $invitation) => [
                'key' => 'invitation-'.$invitation->getKey(),
                'title' => 'Invitation à rejoindre « '.Str::limit($invitation->zumra->name, 40).' »',
                'detail' => ($invitation->inviter ? 'De la part de '.$invitation->inviter->name : 'Une invitation vous attend').', valable jusqu’au '.$invitation->expires_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F'),
                'action' => 'Voir',
                'url' => route('zumras.show', $invitation->zumra),
            ])->all();
    }

    /** Pour le fondateur et les responsables : des demandes d'adhésion attendent une décision (une ligne par ZUMRA). */
    private function joinRequestsToDecide(User $user): array
    {
        return Zumra::query()
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $user->getKey())->whereIn('role', ['founder', 'manager']))
            ->whereHas('joinRequests', fn ($query) => $query->where('state', JoinRequestState::Pending->value)->whereHas('user', fn ($person) => $person->whereNotNull('email_verified_at')))
            ->withCount(['joinRequests as pending_count' => fn ($query) => $query->where('state', JoinRequestState::Pending->value)->whereHas('user', fn ($person) => $person->whereNotNull('email_verified_at'))])
            ->orderBy('name_key')->limit(self::MAX_LINES)->get()
            ->map(fn (Zumra $zumra) => [
                'key' => 'requests-'.$zumra->getKey(),
                'title' => $zumra->pending_count.($zumra->pending_count > 1 ? ' demandes d’adhésion à traiter' : ' demande d’adhésion à traiter'),
                'detail' => 'Dans la ZUMRA « '.Str::limit($zumra->name, 40).' »',
                'action' => 'Voir',
                'url' => route('zumras.show', $zumra),
            ])->all();
    }

    /** Une ligne par publication qui a des réponses en attente et qui peut encore les accepter. */
    private function proposals(User $user): array
    {
        $publications = Publication::query()
            ->where('author_id', $user->getKey())
            ->whereNull('hidden_at')
            ->where(fn ($query) => $query->whereNull('need_state')->orWhereIn('need_state', [NeedState::Open->value, NeedState::InProgress->value]))
            ->whereHas('gestures', fn ($query) => $query->where('state', GestureState::Sent->value))
            ->withCount(['gestures as pending_count' => fn ($query) => $query->where('state', GestureState::Sent->value)])
            ->orderByDesc('created_at')->limit(self::MAX_LINES)->get();

        return $publications->map(fn (Publication $publication) => [
            'key' => 'proposals-'.$publication->getKey(),
            'title' => $publication->pending_count.($publication->pending_count > 1 ? ' réponses à traiter' : ' réponse à traiter'),
            'detail' => 'Sur votre publication « '.Str::limit($publication->body, 40).' »',
            'action' => 'Voir',
            'url' => route('publications.show', $publication),
        ])->all();
    }

    private function unreadMessages(User $user): array
    {
        $id = $user->getKey();

        return Conversation::query()->unreadFor($user)->with(['owner', 'helper'])
            ->orderByDesc('updated_at')->limit(self::MAX_LINES)->get()
            ->map(function (Conversation $conversation) use ($user, $id) {
                $read = $conversation->{$conversation->readColumn($user)};
                $count = Message::query()->where('conversation_id', $conversation->getKey())->whereNotNull('body')
                    ->where('sender_id', '!=', $id)->when($read, fn ($query) => $query->where('created_at', '>', $read))->count();

                return [
                    'key' => 'messages-'.$conversation->getKey(),
                    'title' => $count.($count > 1 ? ' messages non lus' : ' message non lu'),
                    'detail' => $conversation->otherPerson($user)?->name ?? 'Personne supprimée',
                    'action' => 'Lire',
                    'url' => route('conversations.show', $conversation),
                ];
            })->all();
    }

    /** Un besoin résolu récemment, avec une proposition acceptée et sans remerciement. */
    private function thanks(User $user): array
    {
        return Publication::query()
            ->where('author_id', $user->getKey())
            ->where('need_state', NeedState::Resolved->value)
            ->where('resolved_at', '>=', now()->subDays(self::THANK_WITHIN_DAYS))
            ->whereDoesntHave('gratitude')
            ->whereHas('gestures', fn ($query) => $query->where('state', GestureState::Accepted->value))
            ->orderByDesc('resolved_at')->limit(self::MAX_LINES)->get()
            ->map(function (Publication $publication) {
                $helpers = Gesture::query()->with('author')->where('publication_id', $publication->getKey())
                    ->where('state', GestureState::Accepted->value)->orderBy('decided_at')->get();
                $days = (int) $publication->resolved_at->diffInDays(now());

                return [
                    'key' => 'thanks-'.$publication->getKey(),
                    'title' => $helpers->count() === 1 ? 'Remercier '.$helpers->first()->author->name.' ?' : 'Remercier une personne ?',
                    'detail' => $days < 1 ? 'Votre besoin est résolu depuis aujourd’hui' : 'Votre besoin est résolu depuis '.$days.($days > 1 ? ' jours' : ' jour'),
                    'action' => 'Remercier',
                    'url' => route('publications.show', $publication),
                ];
            })->all();
    }
}
