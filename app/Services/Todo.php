<?php

namespace App\Services;

use App\Enums\GestureState;
use App\Enums\JoinRequestState;
use App\Enums\NeedState;
use App\Models\Conversation;
use App\Models\Gesture;
use App\Models\Invitation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Report;
use App\Models\User;
use App\Models\Wallet;
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
            ...$this->moderationNotice($user),
            ...$this->hiddenPublications($user),
            ...$this->reportsToReview($user),
            ...$this->zumraInvitations($user),
            ...$this->joinRequestsToDecide($user),
            ...$this->participationRequestsToDecide($user),
            ...$this->proposals($user),
            ...$this->contributionReminders($user),
            ...$this->unreadMessages($user),
            ...$this->thanks($user),
        ];
    }

    /** Rappel mensuel demandé par la personne ou par sa ZUMRA, sans contribution ce mois-ci : la demande de paiement (Lot 6c, D-163). */
    private function contributionReminders(User $user): array
    {
        if (! app(Wallets::class)->enabled()) {
            return [];
        }

        $contributions = app(Contributions::class);
        $lines = [];

        $owned = Wallet::query()->where('monthly_reminder', true)->whereNull('closed_at')->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('owner_type', Wallet::PERSON)->where('owner_id', $user->getKey()))
            ->orWhere(fn ($q) => $q->where('owner_type', Wallet::ZUMRA)->whereIn('owner_id', $user->managedZumraIds())))->get();

        foreach ($owned as $wallet) {
            if (! $contributions->reminderDue($wallet)) {
                continue;
            }

            $zumra = $wallet->isPerson() ? null : Zumra::query()->find($wallet->owner_id);

            $lines[] = [
                'key' => 'contribution-'.$wallet->getKey(),
                'title' => $zumra ? 'Contribution du mois de '.$zumra->name : 'Votre contribution du mois',
                'detail' => 'Facultative, au montant de votre choix : un signe d’engagement',
                'action' => 'Contribuer',
                'url' => $zumra ? route('zumras.contribution', $zumra) : route('contribution'),
            ];
        }

        return $lines;
    }

    /** La modération a envoyé un avis à la personne, qui n'y a pas encore répondu (D-145). */
    private function moderationNotice(User $user): array
    {
        $notice = $user->openNotice();

        if (! $notice || $notice->isExplained()) {
            return [];
        }

        return [[
            'key' => 'notice-'.$notice->getKey(),
            'title' => 'Un avis de la modération vous attend',
            'detail' => 'Vous pouvez vous expliquer jusqu’au '.$notice->deadline()->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F'),
            'action' => 'Expliquer',
            'url' => route('avis'),
        ]];
    }

    /** Une publication de la personne a été masquée et la modération attend son explication (écran 14). */
    private function hiddenPublications(User $user): array
    {
        return Publication::query()
            ->managedBy($user)
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
            ->whereHas('joinRequests', fn ($query) => $query->where('state', JoinRequestState::Pending->value)->whereHas('user', fn ($person) => $person->active()))
            ->withCount(['joinRequests as pending_count' => fn ($query) => $query->where('state', JoinRequestState::Pending->value)->whereHas('user', fn ($person) => $person->active())])
            ->orderBy('name_key')->limit(self::MAX_LINES)->get()
            ->map(fn (Zumra $zumra) => [
                'key' => 'requests-'.$zumra->getKey(),
                'title' => $zumra->pending_count.($zumra->pending_count > 1 ? ' demandes d’adhésion à traiter' : ' demande d’adhésion à traiter'),
                'detail' => 'Dans la ZUMRA « '.Str::limit($zumra->name, 40).' »',
                'action' => 'Voir',
                'url' => route('zumras.show', $zumra),
            ])->all();
    }

    /** Pour le fondateur et les responsables : des demandes de participation à un projet attendent une décision (une ligne par projet). */
    private function participationRequestsToDecide(User $user): array
    {
        $pending = fn ($query) => $query->where('state', JoinRequestState::Pending->value)->whereHas('user', fn ($person) => $person->active());

        return Project::query()
            ->whereIn('zumra_id', $user->managedZumraIds())
            ->whereHas('participationRequests', $pending)
            ->withCount(['participationRequests as pending_count' => $pending])
            ->orderBy('name_key')->limit(self::MAX_LINES)->get()
            ->map(fn (Project $project) => [
                'key' => 'participation-'.$project->getKey(),
                'title' => $project->pending_count.($project->pending_count > 1 ? ' demandes de participation à traiter' : ' demande de participation à traiter'),
                'detail' => 'Dans le projet « '.Str::limit($project->name, 40).' »',
                'action' => 'Voir',
                'url' => route('projets.show', ['project' => $project, 'onglet' => 'equipe']),
            ])->all();
    }

    /** Une ligne par publication qui a des réponses en attente et qui peut encore les accepter. */
    private function proposals(User $user): array
    {
        $publications = Publication::query()
            ->managedBy($user)
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
            ->managedBy($user)
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
