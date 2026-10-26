<?php

namespace App\Services;

use App\Models\PersonConnection;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Personnes dont les capacités complètent les miennes : l'autre apporte ce que je veux apprendre, ou veut apprendre ce que
 * j'apporte. Un seul endroit pour le Fil, le Carrefour Personnes et Mon espace (audit A12). Seuls des profils réels que la
 * personne a le droit de voir (visibilité, blocages, comptes actifs : Profile::visibleTo), hors elle-même ; ordre alphabétique,
 * jamais un classement ni un score ; la raison affichée ne cite que des capacités déjà publiques du profil suggéré.
 */
class ComplementaryPeople
{
    /** Identifiants des personnes déjà en relation (acceptée ou demandée), et facultativement de celles que l'on suit. @return array<int, string> */
    public function relatedUserIds(User $viewer, bool $withFollowed = false): array
    {
        $ids = PersonConnection::query()->forPerson($viewer)->whereIn('state', ['accepted', 'pending'])->get()
            ->map(fn (PersonConnection $connection) => $connection->otherId($viewer))->all();

        if ($withFollowed) {
            $ids = array_merge($ids, DB::table('person_follows')->where('user_id', $viewer->id)->pluck('followed_id')->all());
        }

        return array_values(array_unique($ids));
    }

    /** Profils visibles par cette personne, hors elle-même et hors les identifiants exclus, par ordre alphabétique. */
    public function candidates(User $viewer, array $excludeUserIds = []): Builder
    {
        return Profile::query()->visibleTo($viewer)->where('profiles.user_id', '<>', $viewer->id)
            ->whereNotIn('profiles.user_id', $excludeUserIds ?: [Str::uuid()->toString()])
            ->orderBy(User::query()->select('name_search')->whereColumn('users.id', 'profiles.user_id'));
    }

    /** Limite la requête aux profils complémentaires de `$mine` ; sans capacité déclarée, rien n'est suggéré. */
    public function constrain(Builder $profiles, ?Profile $mine): void
    {
        $wanted = $mine?->wantedSkills->pluck('id')->all() ?? [];
        $offered = $mine?->offeredSkills->pluck('id')->all() ?? [];

        if ($wanted === [] && $offered === []) {
            $profiles->whereRaw('1 = 0');

            return;
        }

        $profiles->where(function (Builder $query) use ($wanted, $offered) {
            if ($wanted !== []) {
                $query->orWhereHas('offeredSkills', fn (Builder $skills) => $skills->whereIn('skills.id', $wanted));
            }
            if ($offered !== []) {
                $query->orWhereHas('wantedSkills', fn (Builder $skills) => $skills->whereIn('skills.id', $offered));
            }
        });
    }

    /**
     * Quelques personnes complémentaires, chacune avec sa `reason`. `$explainLearning` précise « que vous pouvez apporter » quand
     * l'autre veut apprendre ce que j'apporte.
     *
     * @param  array<int, string>  $excludeUserIds
     * @param  array<int, string>  $with
     * @return Collection<int, Profile>
     */
    public function suggestions(User $viewer, Profile $mine, array $excludeUserIds, int $limit, array $with = ['user', 'city', 'offeredSkills', 'wantedSkills'], bool $explainLearning = false): Collection
    {
        $mine->loadMissing(['offeredSkills', 'wantedSkills']);
        $query = $this->candidates($viewer, $excludeUserIds);
        $this->constrain($query, $mine);

        $wanted = $mine->wantedSkills->pluck('id');
        $offered = $mine->offeredSkills->pluck('id');

        return $query->with(array_values(array_unique([...$with, 'offeredSkills', 'wantedSkills'])))->limit($limit)->get()
            ->map(function (Profile $person) use ($wanted, $offered, $explainLearning) {
                $brings = $person->offeredSkills->whereIn('id', $wanted->all())->pluck('name')->first();
                $learns = $person->wantedSkills->whereIn('id', $offered->all())->pluck('name')->first();
                $person->setAttribute('reason', $brings ? 'Peut vous apporter : '.$brings : 'Veut apprendre : '.$learns.($explainLearning ? ', que vous pouvez apporter' : ''));

                return $person;
            });
    }
}
