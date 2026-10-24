<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Enums\PublicationType;
use App\Models\MatchSuggestion;
use App\Models\Profile;
use App\Models\Publication;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/** D-187 : complémentarités explicables, données choisies et accès courants. Aucun score de personne. */
class Matching
{
    private const POST_RELATIONS = ['author.profile', 'skills', 'city'];

    private const PROFILE_RELATIONS = ['user', 'offeredSkills', 'wantedSkills'];

    public function preferences(Profile $profile): array
    {
        return array_replace(MatchingConditions::defaults(), $profile->matching_preferences ?? []);
    }

    public function settings(Publication $publication): array
    {
        return array_replace(MatchingConditions::publicationDefaults($publication->type), $publication->matching ?? []);
    }

    public function publicationChanged(Publication $publication): void
    {
        $this->safely(function () use ($publication) {
            if (! $this->active($publication)) {
                return;
            }
            $publication->loadMissing(self::POST_RELATIONS);
            $relation = $this->profileRelation($publication);
            if ($relation !== null) {
                Profile::query()->where('user_id', '!=', $publication->author_id)->where('matching_preferences->enabled', true)
                    ->whereHas($relation, fn (Builder $q) => $q->whereIn('skills.id', $publication->skills->modelKeys()))
                    ->with(self::PROFILE_RELATIONS)->chunkById(100, function ($profiles) use ($publication) {
                        foreach ($profiles as $profile) {
                            $this->rememberPerson($publication, $profile);
                        }
                    });
            }
            if (in_array($publication->type, [PublicationType::Need, PublicationType::Offer], true) && $this->settings($publication)['activity'] === 'service') {
                $opposite = $publication->type === PublicationType::Need ? PublicationType::Offer : PublicationType::Need;
                $this->posts()->where('type', $opposite)->where('author_id', '!=', $publication->author_id)
                    ->whereHas('skills', fn (Builder $q) => $q->whereIn('skills.id', $publication->skills->modelKeys()))
                    ->chunkById(100, function ($posts) use ($publication) {
                        foreach ($posts as $post) {
                            $this->rememberOffer($publication->isNeed() ? $publication : $post, $publication->isNeed() ? $post : $publication);
                        }
                    });
            }
        });
    }

    public function profileChanged(string $userId): void
    {
        $this->safely(function () use ($userId) {
            $user = User::find($userId);
            if (! $user) {
                return;
            }
            $prefs = $this->preferences($user->ensureProfile());
            if (! $prefs['enabled'] || ! $prefs['notify']) {
                UserNotification::where('user_id', $userId)->where('kind', NotificationKind::MatchFound)->whereNull('read_at')->update(['read_at' => now()]);
            }
            $this->discover($user, 100);
        });
    }

    /** Recalcul de données dérivées ; une panne ne transforme pas un profil enregistré en faux échec d’envoi. */
    private function safely(callable $work): void
    {
        try {
            $work();
        } catch (Throwable $error) {
            report($error);
        }
    }

    private function posts(): Builder
    {
        return Publication::query()->whereNull('zumra_id')->whereNull('project_id')->whereNull('hidden_at')
            ->where('matching->enabled', true)->where('matching->open', true)->with(self::POST_RELATIONS);
    }

    public function suggestions(User $user, int $limit = 10): Collection
    {
        $this->discover($user, min(101, max(1, $limit + 1)));
        $items = collect();
        foreach (MatchSuggestion::where('user_id', $user->id)->whereNull('ignored_at')->with(['publication.author.profile', 'publication.skills', 'publication.city', 'profile.user', 'profile.offeredSkills', 'profile.wantedSkills', 'offer.author.profile', 'offer.skills', 'offer.city'])->orderByDesc(Publication::select('created_at')->whereColumn('publications.id', 'match_suggestions.publication_id'))->orderByDesc('id')->lazy(100) as $row) {
            $detail = $this->detail($user, $row);
            if ($detail) {
                $items->push(['suggestion' => $row, ...$detail]);
            }
            if ($items->count() > $limit) {
                break;
            }
        }

        return $items;
    }

    private function discover(User $user, int $limit): void
    {
        $profile = $user->ensureProfile()->loadMissing(self::PROFILE_RELATIONS);
        if (! $user->isActive() || ! $this->ready($profile)) {
            return;
        }
        $skills = $profile->offeredSkills->modelKeys();
        $skills = array_unique([...$skills, ...$profile->wantedSkills->modelKeys()]);
        $count = 0;
        foreach ($this->posts()->visibleTo($user)->where('author_id', '!=', $user->id)->whereIn('matching->terms', $this->preferences($profile)['terms'])
            ->whereHas('skills', fn (Builder $q) => $q->whereIn('skills.id', $skills))->orderByDesc('created_at')->orderByDesc('id')->lazy(100) as $post) {
            if ($this->personReasons($post, $profile) && $this->rememberPerson($post, $profile, $user->id)) {
                $count++;
            }
            if ($count >= $limit) {
                break;
            }
        }
        foreach ($this->posts()->where('author_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')->lazy(50) as $post) {
            if (! $this->active($post)) {
                continue;
            }
            $relation = $this->profileRelation($post);
            if ($relation !== null) {
                foreach (Profile::visibleTo($user)->where('user_id', '!=', $user->id)->where('matching_preferences->enabled', true)
                    ->whereHas($relation, fn (Builder $q) => $q->whereIn('skills.id', $post->skills->modelKeys()))
                    ->with(self::PROFILE_RELATIONS)->orderByDesc('created_at')->orderByDesc('id')->lazy(100) as $candidate) {
                    if ($this->personReasons($post, $candidate) && $this->rememberPerson($post, $candidate, $user->id)) {
                        $count++;
                    }
                    if ($count >= $limit) {
                        break;
                    }
                }
            }
            if (in_array($post->type, [PublicationType::Need, PublicationType::Offer], true) && $this->settings($post)['activity'] === 'service') {
                $opposite = $post->isNeed() ? PublicationType::Offer : PublicationType::Need;
                foreach ($this->posts()->visibleTo($user)->where('type', $opposite)->where('author_id', '!=', $user->id)
                    ->whereHas('skills', fn (Builder $q) => $q->whereIn('skills.id', $post->skills->modelKeys()))->orderByDesc('created_at')->orderByDesc('id')->lazy(100) as $other) {
                    $need = $post->isNeed() ? $post : $other;
                    $offer = $post->isNeed() ? $other : $post;
                    if ($this->offerReasons($need, $offer) && $this->rememberOffer($need, $offer, $user->id)) {
                        $count++;
                    }
                    if ($count >= $limit) {
                        break;
                    }
                }
            }
            if ($count >= $limit) {
                break;
            }
        }
    }

    private function rememberPerson(Publication $post, Profile $profile, ?string $countFor = null): bool
    {
        if (! $this->personReasons($post, $profile)) {
            return false;
        }
        $found = false;
        foreach (array_unique([$post->author_id, $profile->user_id]) as $userId) {
            $row = $this->remember($userId, $post->id, $profile->id, null);
            if ($userId === $countFor && $row && ! $row->ignored_at) {
                $found = true;
            }
        }

        return $found;
    }

    private function rememberOffer(Publication $need, Publication $offer, ?string $countFor = null): bool
    {
        if (! $this->offerReasons($need, $offer)) {
            return false;
        }
        $found = false;
        foreach (array_unique([$need->author_id, $offer->author_id]) as $userId) {
            $row = $this->remember($userId, $need->id, null, $offer->id);
            if ($userId === $countFor && $row && ! $row->ignored_at) {
                $found = true;
            }
        }

        return $found;
    }

    private function remember(string $userId, string $postId, ?string $profileId, ?string $offerId): ?MatchSuggestion
    {
        return DB::transaction(function () use ($userId, $postId, $profileId, $offerId) {
            $user = User::whereKey($userId)->lockForUpdate()->first();
            if (! $user?->isActive() || ! $this->ready($user->ensureProfile())) {
                return null;
            }
            $values = ['publication_id' => $postId, 'profile_id' => $profileId, 'offer_id' => $offerId];
            $draft = new MatchSuggestion(['user_id' => $userId, ...$values]);
            if (! $this->detail($user, $draft, fresh: true)) {
                return null;
            }
            $row = MatchSuggestion::firstOrCreate(['user_id' => $userId, 'signature' => hash('sha256', $postId.'|'.$profileId.'|'.$offerId)], $values);
            if ($row->ignored_at || $row->alerted_at || ! $this->preferences($user->profile)['notify']) {
                return $row;
            }
            $pending = UserNotification::where('user_id', $userId)->where('kind', NotificationKind::MatchFound)->whereNull('read_at')->exists();
            $sentToday = UserNotification::where('user_id', $userId)->where('kind', NotificationKind::MatchFound)->where('created_at', '>=', now('Africa/Abidjan')->startOfDay())->count();
            if (! $pending && $sentToday < 5) {
                app(Notifier::class)->push($user, NotificationKind::MatchFound);
            }
            // Une correspondance déjà regroupée dans une alerte ne produit pas de rappel après lecture.
            $row->update(['alerted_at' => now()]);

            return $row;
        });
    }

    public function detail(User $user, MatchSuggestion $row, bool $fresh = false): ?array
    {
        if (! Gate::forUser($user)->allows('view', $row) || ! $this->ready($user->ensureProfile())) {
            return null;
        }
        $post = $row->publication;
        if (! $post || ! $this->active($post) || ! Gate::forUser($user)->allows('view', $post)) {
            return null;
        }
        if ($row->profile_id) {
            $profile = $row->profile;
            if (! $profile || ! in_array($user->id, [$post->author_id, $profile->user_id], true) || ! Gate::forUser($user)->allows('view', $profile)) {
                return null;
            }
            if (People::blocked($post->author, $profile->user, fresh: $fresh)) {
                return null;
            }
            $reasons = $this->personReasons($post, $profile);
            if (! $reasons) {
                return null;
            }
            $own = $post->author_id === $user->id;

            return ['title' => $own ? $profile->user->name : $post->type->label(), 'body' => $own ? $profile->bio : $post->body, 'url' => $own ? route('personnes.show', $profile) : route('publications.show', $post), 'reasons' => $reasons, 'context' => $own ? $post : null, 'person' => $own ? $profile : $post->author->profile];
        }
        $offer = $row->offer;
        if (! $offer || ! in_array($user->id, [$post->author_id, $offer->author_id], true) || ! Gate::forUser($user)->allows('view', $offer)) {
            return null;
        }
        if (People::blocked($post->author, $offer->author, fresh: $fresh)) {
            return null;
        }
        $target = $post->author_id === $user->id ? $offer : $post;
        if (! in_array($this->settings($target)['terms'], $this->preferences($user->profile)['terms'], true)) {
            return null;
        }
        $reasons = $this->offerReasons($post, $offer);

        return $reasons ? ['title' => $target->type->label(), 'body' => $target->body, 'url' => route('publications.show', $target), 'reasons' => $reasons, 'context' => null, 'person' => $target->author->profile] : null;
    }

    public function ignore(User $user, MatchSuggestion $row): void
    {
        Gate::forUser($user)->authorize('ignore', $row);
        $row->update(['ignored_at' => now()]);
    }

    private function ready(Profile $profile): bool
    {
        $prefs = $this->preferences($profile);

        return $prefs['enabled'] && $prefs['available'] && (! $prefs['until'] || $prefs['until'] >= today('Africa/Abidjan')->toDateString());
    }

    private function active(Publication $post): bool
    {
        $cfg = $this->settings($post);

        return ! $post->zumra_id && ! $post->project_id && ! $post->isHidden() && $post->author->isActive() && $cfg['enabled'] && $cfg['open'] && $this->preferences($post->author->ensureProfile())['available'] && (! $cfg['until'] || $cfg['until'] >= today('Africa/Abidjan')->toDateString()) && (! $post->isNeed() || $post->need_state?->isActive());
    }

    private function profileRelation(Publication $post): ?string
    {
        return match ($post->type) {
            PublicationType::Need, PublicationType::LearningWish => 'offeredSkills',
            PublicationType::Knowledge => 'wantedSkills',
            PublicationType::Offer => $this->settings($post)['activity'] === 'training' ? 'wantedSkills' : null,
            default => null,
        };
    }

    public function personReasons(Publication $post, Profile $profile): ?array
    {
        if (! $this->active($post) || ! $profile->user->isActive() || $post->author_id === $profile->user_id || ! $this->ready($profile)) {
            return null;
        }
        if (! Gate::forUser($profile->user)->allows('view', $post) || ! Gate::forUser($post->author)->allows('view', $profile) || People::blocked($post->author, $profile->user)) {
            return null;
        }
        $cfg = $this->settings($post);
        $prefs = $this->preferences($profile);
        $intention = match ($post->type) {
            PublicationType::Need => 'offer', PublicationType::LearningWish => 'teach',
            PublicationType::Knowledge => 'learn', PublicationType::Offer => $cfg['activity'] === 'training' ? 'learn' : null,
            default => null,
        };
        if (! $intention || ! in_array($intention, $prefs['intentions'], true) || ! in_array($cfg['terms'], $prefs['terms'], true)) {
            return null;
        }
        $skills = $post->skills->whereIn('id', $profile->{$this->profileRelation($post)}->modelKeys())->pluck('name')->all();
        if ($skills === []) {
            return null;
        }
        $place = $post->city?->active && in_array($post->city_id, $prefs['zones'], true) ? 'Localité choisie : '.$post->city->label() : ($cfg['remote'] && $prefs['remote'] ? 'À distance possible des deux côtés' : null);

        return $this->reasons($skills, $place, $cfg, $prefs);
    }

    public function offerReasons(Publication $need, Publication $offer): ?array
    {
        if (! $need->isNeed() || $offer->type !== PublicationType::Offer || $need->author_id === $offer->author_id || ! $this->active($need) || ! $this->active($offer)) {
            return null;
        }
        if (! Gate::forUser($need->author)->allows('view', $offer) || ! Gate::forUser($offer->author)->allows('view', $need) || People::blocked($need->author, $offer->author)) {
            return null;
        }
        $a = $this->settings($need);
        $b = $this->settings($offer);
        if ($a['activity'] !== 'service' || $b['activity'] !== 'service' || ($a['terms'] !== $b['terms'] && ! in_array('discuss', [$a['terms'], $b['terms']], true))) {
            return null;
        }
        $skills = $need->skills->whereIn('id', $offer->skills->modelKeys())->pluck('name')->all();
        if ($skills === []) {
            return null;
        }
        $place = $need->city?->active && $need->city_id === $offer->city_id ? 'Même localité : '.$need->city->label() : ($a['remote'] && $b['remote'] ? 'À distance possible des deux côtés' : null);
        if ($a['terms'] !== $b['terms']) {
            $a['terms'] = 'discuss';
        }

        return $this->reasons($skills, $place, $a, $b);
    }

    private function reasons(array $skills, ?string $place, array $a, array $b): ?array
    {
        if (! $place || ($a['from'] && $b['until'] && $a['from'] > $b['until']) || ($b['from'] && $a['until'] && $b['from'] > $a['until']) || ($a['days'] && $b['days'] && array_intersect($a['days'], $b['days']) === [])) {
            return null;
        }

        $start = max([today('Africa/Abidjan')->toDateString(), $a['from'], $b['from']]);
        $ends = array_filter([$a['until'], $b['until']]);
        $end = $ends ? min($ends) : null;
        $days = $a['days'] && $b['days'] ? array_values(array_intersect($a['days'], $b['days'])) : ($a['days'] ?: $b['days']);
        if ($end && $days) {
            $possible = false;
            $date = CarbonImmutable::parse($start, 'Africa/Abidjan');
            for ($i = 0; $i < 7 && $date->toDateString() <= $end; $i++, $date = $date->addDay()) {
                if (in_array($date->dayOfWeekIso, $days, true)) {
                    $possible = true;
                    break;
                }
            }
            if (! $possible) {
                return null;
            }
        }

        return ['Capacité commune : '.implode(', ', $skills), $place, MatchingConditions::TERMS[$a['terms']], $a['from'] && $a['until'] && $b['from'] && $b['until'] ? 'Les périodes déclarées se recoupent' : 'Disponibilités à préciser ensemble'];
    }
}
