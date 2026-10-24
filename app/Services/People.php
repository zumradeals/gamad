<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Models\Conversation;
use App\Models\PersonConnection;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** D-186 : suivre n'autorise aucun contact. Seul un consentement réciproque ouvre la conversation. */
class People
{
    public static function blocked(User $a, User $b, bool $fresh = false): bool
    {
        if (! $fresh) {
            return in_array($b->id, self::excludedIds($a), true);
        }

        return DB::table('person_blocks')->where(fn ($q) => $q->where('user_id', $a->id)->where('blocked_id', $b->id))
            ->orWhere(fn ($q) => $q->where('user_id', $b->id)->where('blocked_id', $a->id))->exists();
    }

    public static function excludedIds(User $user): array
    {
        $key = 'gamad.blocks.'.$user->id;
        if (! request()->attributes->has($key)) {
            request()->attributes->set($key, DB::table('person_blocks')->where('user_id', $user->id)->pluck('blocked_id')
                ->merge(DB::table('person_blocks')->where('blocked_id', $user->id)->pluck('user_id'))->unique()->all());
        }

        return request()->attributes->get($key);
    }

    public function follow(User $actor, User $other, bool $follow): void
    {
        $this->locked($actor, $other, function () use ($actor, $other, $follow) {
            if ($follow) {
                $this->authorize($actor, $other);
                DB::table('person_follows')->insertOrIgnore(['user_id' => $actor->id, 'followed_id' => $other->id, 'created_at' => now()]);
            } else {
                DB::table('person_follows')->where('user_id', $actor->id)->where('followed_id', $other->id)->delete();
            }
        });
    }

    public function request(User $actor, User $other, string $note): void
    {
        $note = Normalizer::multiline($note);
        if ($note === '' || mb_strlen($note) > 280) {
            throw ValidationException::withMessages(['note' => 'Expliquez votre envie de faire connaissance en 1 à 280 caractères.']);
        }
        $this->locked($actor, $other, function () use ($actor, $other, $note) {
            $this->authorize($actor, $other);
            if (RateLimiter::tooManyAttempts('connection:'.$actor->id, 20)) {
                throw ValidationException::withMessages(['note' => 'Vous avez atteint la limite de demandes pour aujourd’hui.']);
            }
            $pair = $this->pair($actor, $other)->first();
            if ($pair && (in_array($pair->state, ['pending', 'accepted'], true) || $pair->updated_at->gt(now()->subDays(7)))) {
                throw ValidationException::withMessages(['note' => 'Une demande existe déjà ou a été traitée récemment.']);
            }
            $ids = [$actor->id, $other->id];
            sort($ids);
            PersonConnection::query()->updateOrCreate(['first_id' => $ids[0], 'second_id' => $ids[1]], ['sender_id' => $actor->id, 'state' => 'pending', 'note' => $note, 'revision' => ($pair?->revision ?? 0) + 1]);
            RateLimiter::hit('connection:'.$actor->id, 86400);
            app(Notifier::class)->push($other, NotificationKind::ConnectionRequested, $actor);
        });
    }

    public function decide(User $actor, User $other, string $decision, int $revision): void
    {
        abort_unless(in_array($decision, ['accept', 'decline', 'withdraw', 'remove'], true), 422);
        $this->locked($actor, $other, function () use ($actor, $other, $decision, $revision) {
            $pair = $this->pair($actor, $other)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isActive(), 403);
            abort_unless((int) $pair->revision === $revision, 409, 'Cette demande a changé. Rechargez la page avant de décider.');
            $pair->increment('revision');
            if ($decision === 'remove') {
                abort_unless($pair->state === 'accepted', 409);
                $pair->update(['state' => 'removed', 'note' => null]);

                return;
            }
            abort_unless($pair->state === 'pending', 409);
            abort_unless(($decision === 'withdraw') === ($pair->sender_id === $actor->id), 403);
            if ($decision === 'accept') {
                $this->authorize($actor, $other);
                $conversation = $pair->conversation_id ? Conversation::find($pair->conversation_id) : null;
                $conversation ??= Conversation::create(['owner_id' => $actor->id, 'helper_id' => $other->id, 'origin' => 'relation']);
                $pair->update(['state' => 'accepted', 'conversation_id' => $conversation->id]);
                app(Notifier::class)->push($other, NotificationKind::ConnectionAccepted, $actor, conversation: $conversation);
            } else {
                $pair->update(['state' => $decision === 'decline' ? 'declined' : 'withdrawn', 'note' => null]);
            }
        });
    }

    public function block(User $actor, User $other, bool $block): void
    {
        $this->locked($actor, $other, function () use ($actor, $other, $block) {
            abort_unless($actor->isActive(), 403);
            request()->attributes->remove('gamad.blocks.'.$actor->id);
            request()->attributes->remove('gamad.blocks.'.$other->id);
            if (! $block) {
                DB::table('person_blocks')->where('user_id', $actor->id)->where('blocked_id', $other->id)->delete();

                return;
            }
            DB::table('person_blocks')->insertOrIgnore(['user_id' => $actor->id, 'blocked_id' => $other->id, 'created_at' => now()]);
            DB::table('person_follows')->where(fn ($q) => $q->where('user_id', $actor->id)->where('followed_id', $other->id))
                ->orWhere(fn ($q) => $q->where('user_id', $other->id)->where('followed_id', $actor->id))->delete();
            $this->pair($actor, $other)->update(['state' => 'removed', 'note' => null, 'revision' => DB::raw('revision + 1')]);
        });
    }

    public function pair(User $a, User $b)
    {
        $ids = [$a->id, $b->id];
        sort($ids);

        return PersonConnection::query()->where('first_id', $ids[0])->where('second_id', $ids[1]);
    }

    private function authorize(User $actor, User $other): void
    {
        abort_unless($actor->isActive() && $other->isActive() && ! self::blocked($actor, $other, fresh: true), 403);
        Gate::forUser($actor)->authorize('view', $other->ensureProfile());
    }

    private function locked(User $actor, User $other, callable $callback): void
    {
        abort_if($actor->id === $other->id, 403);
        DB::transaction(function () use ($actor, $other, $callback) {
            // Ordre stable : les demandes croisées et les blocages se sérialisent même sans ligne de relation.
            $users = User::query()->whereIn('id', [$actor->id, $other->id])->orderBy('id')->lockForUpdate()->get();
            abort_unless($users->count() === 2, 404);
            $actor->setRawAttributes($users->firstWhere('id', $actor->id)->getAttributes(), true);
            $other->setRawAttributes($users->firstWhere('id', $other->id)->getAttributes(), true);
            $callback();
        });
    }
}
