<?php

namespace App\Livewire\Concerns;

use App\Models\Skill;
use App\Support\Normalizer;
use App\Support\SkillList;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sélecteur de capacités d'un écran Livewire : une ou plusieurs listes de noms (« offered », « wanted », « skills »…), chacune avec
 * son champ de saisie `{liste}Input`, ses suggestions de capacités déjà utilisées et sa limite. Les capacités nouvelles ne sont
 * créées qu'à l'enregistrement de l'écran : rien ne reste orphelin si on abandonne.
 *
 * L'écran déclare ses listes avec `skillLists()` : nom de la liste => nombre maximal.
 */
trait PicksSkills
{
    /** @return array<string, int> */
    abstract protected function skillLists(): array;

    /** Ajoute une capacité à une liste : celle qu'on touche dans les suggestions, ou le texte saisi. */
    public function addSkill(string $list, ?string $name = null): void
    {
        $max = $this->skillMax($list);
        $input = $list.'Input';
        $this->resetErrorBag([$input, $list]);
        $display = Normalizer::display($name ?? $this->{$input});

        if ($display === '') {
            return;
        }

        if (! SkillList::isValid($display)) {
            $this->addError($input, SkillList::invalidMessage());

            return;
        }

        $key = Normalizer::key($display);

        if (in_array($key, array_map(Normalizer::key(...), $this->{$list}), true)) {
            $this->{$input} = '';

            return;
        }

        if (count($this->{$list}) >= $max) {
            $this->addError($input, self::skillLimitMessage($max));

            return;
        }

        // Si la capacité existe déjà, on garde son écriture d'origine (D-066) : « COMPTABILITE » devient « Comptabilité ».
        $existing = Skill::query()->where('normalized', $key)->value('name');

        $this->{$list}[] = $existing ?? $display;
        $this->{$input} = '';
    }

    public function removeSkill(string $list, int $index): void
    {
        $this->skillMax($list);

        unset($this->{$list}[$index]);
        $this->{$list} = array_values($this->{$list});
        $this->resetErrorBag([$list.'Input', $list]);
    }

    public static function skillLimitMessage(int $max): string
    {
        $words = [3 => 'Trois', 10 => 'Dix'];

        return ($words[$max] ?? $max).' capacités au plus : retirez-en une pour en ajouter.';
    }

    /**
     * Capacités déjà utilisées (par un profil, une publication ou une opportunité) qui contiennent la saisie, celles qui commencent par la saisie
     * d'abord. Une capacité que plus personne n'utilise n'est plus proposée.
     *
     * @return Collection<int, Skill>
     */
    protected function skillSuggestions(string $list)
    {
        $key = Normalizer::key($this->{$list.'Input'});

        if (mb_strlen($key) < 1) {
            return collect();
        }

        $selected = array_map(Normalizer::key(...), $this->{$list});

        return Skill::query()
            ->where(fn ($query) => $query
                ->whereExists(fn ($used) => $used->select(DB::raw(1))->from('profile_skill')->whereColumn('profile_skill.skill_id', 'skills.id'))
                ->orWhereExists(fn ($used) => $used->select(DB::raw(1))->from('publication_skill')->whereColumn('publication_skill.skill_id', 'skills.id'))
                ->orWhereExists(fn ($used) => $used->select(DB::raw(1))->from('opportunity_skill')->whereColumn('opportunity_skill.skill_id', 'skills.id'))
                ->orWhereExists(fn ($used) => $used->select(DB::raw(1))->from('project_skill')->whereColumn('project_skill.skill_id', 'skills.id')))
            ->where('normalized', 'like', Normalizer::likeContains($key))
            ->whereNotIn('normalized', $selected)
            ->orderByRaw('case when normalized like ? then 0 else 1 end', [addcslashes($key, '\\%_').'%'])
            ->orderBy('normalized')
            ->limit(6)
            ->get();
    }

    private function skillMax(string $list): int
    {
        return $this->skillLists()[$list] ?? abort(404);
    }
}
