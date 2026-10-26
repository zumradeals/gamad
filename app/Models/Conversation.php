<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Échange privé entre deux personnes, ouvert par un geste accepté (PRODUIT §4). L'auteur de la publication (owner) et la
 * personne dont le geste est accepté (helper) en sont les seuls participants. Si l'une des deux supprime son compte, la
 * conversation reste lisible par l'autre (D-097).
 */
class Conversation extends Model
{
    use HasUuids;

    public const MESSAGE_MAX = 1000;

    public const ACTION_MAX = 280;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['owner_read_at' => 'datetime', 'helper_read_at' => 'datetime', 'next_action_date' => 'date', 'next_action_confirmed_at' => 'datetime', 'next_action_revision' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'helper_id');
    }

    public function gesture(): BelongsTo
    {
        return $this->belongsTo(Gesture::class);
    }

    public function contributionProposal(): BelongsTo
    {
        return $this->belongsTo(ContributionProposal::class);
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isParticipant(?User $user): bool
    {
        return $user !== null && in_array($user->getKey(), [$this->owner_id, $this->helper_id], true);
    }

    /** L'autre personne de la conversation vue par `$user` (null si elle a supprimé son compte). */
    public function otherPerson(User $user): ?User
    {
        return $user->getKey() === $this->owner_id ? $this->helper : $this->owner;
    }

    /** Colonne « lu jusqu'à » de `$user`. */
    public function readColumn(User $user): string
    {
        return $user->getKey() === $this->owner_id ? 'owner_read_at' : 'helper_read_at';
    }

    /**
     * Conversations de `$user` qui contiennent au moins un message d'un autre, plus récent que sa dernière lecture.
     * Les messages effacés avec un compte (corps vide) ne comptent pas.
     */
    public function scopeUnreadFor($query, User $user)
    {
        $id = $user->getKey();

        return $query->where(function ($query) use ($id) {
            $query->where('owner_id', $id)->orWhere('helper_id', $id);
        })->whereExists(function ($sub) use ($id) {
            $sub->selectRaw('1')->from('messages')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->whereNotNull('messages.body')
                ->where('messages.sender_id', '!=', $id)
                ->whereRaw('messages.created_at > coalesce(case when conversations.owner_id = ? then conversations.owner_read_at else conversations.helper_read_at end, ?)', [$id, '1970-01-01 00:00:00']);
        });
    }
}
