<?php

namespace App\Services;

use App\Enums\PublicationType;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Données choisies, bornées, sans interprétation du texte libre ni géolocalisation. */
class MatchingConditions
{
    public const INTENTIONS = ['offer' => 'Proposer mes compétences (missions ou aide)', 'teach' => 'Transmettre un savoir', 'learn' => 'Apprendre'];

    public const TERMS = ['volunteer' => 'Bénévole / gratuit', 'paid' => 'Rémunéré / payant', 'discuss' => 'À convenir explicitement'];

    public const DAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    public static function defaults(): array
    {
        return ['enabled' => false, 'notify' => false, 'available' => true, 'intentions' => [], 'terms' => [], 'zones' => [], 'remote' => false, 'from' => '', 'until' => '', 'days' => [], 'note' => ''];
    }

    public static function publicationDefaults(PublicationType $type): array
    {
        return ['enabled' => false, 'open' => true, 'activity' => in_array($type, [PublicationType::Knowledge, PublicationType::LearningWish], true) ? 'training' : 'service', 'terms' => 'discuss', 'budget' => '', 'remote' => false, 'from' => '', 'until' => '', 'days' => [], 'note' => ''];
    }

    public function preferences(array $data): array
    {
        $data = $this->validate($data, true);
        if ($data['enabled'] && ($data['intentions'] === [] || $data['terms'] === [] || (! $data['remote'] && $data['zones'] === []))) {
            throw ValidationException::withMessages(['matching_preferences' => 'Choisissez au moins une intention, une condition et une localité, ou acceptez les échanges à distance.']);
        }
        if (! $data['enabled']) {
            $data['notify'] = false;
        }

        return $data;
    }

    public function publication(array $data, PublicationType $type, ?string $cityId, array $skills): array
    {
        $data = $this->validate($data, false);
        if ($type === PublicationType::Need) {
            $data['activity'] = 'service';
        }
        if (in_array($type, [PublicationType::Knowledge, PublicationType::LearningWish], true)) {
            $data['activity'] = 'training';
        }
        if ($data['enabled'] && (! in_array($type, [PublicationType::Need, PublicationType::Offer, PublicationType::Knowledge, PublicationType::LearningWish], true) || $skills === [] || (! $cityId && ! $data['remote']))) {
            throw ValidationException::withMessages(['matching' => 'Pour le matching, indiquez une capacité et une localité ou la possibilité à distance sur un besoin, une offre ou un apprentissage.']);
        }
        if ($data['terms'] !== 'paid' && $data['budget'] !== '') {
            throw ValidationException::withMessages(['matching.budget' => 'Un budget concerne uniquement une occasion rémunérée ou payante.']);
        }

        return $data;
    }

    private function validate(array $data, bool $profile): array
    {
        $root = $profile ? 'matching_preferences' : 'matching';
        $defaults = $profile ? self::defaults() : self::publicationDefaults(PublicationType::Need);
        $rules = ['enabled' => ['required', 'boolean'], 'remote' => ['required', 'boolean'], 'from' => ['nullable', 'date_format:Y-m-d'], 'until' => ['nullable', 'date_format:Y-m-d'], 'days' => ['array', 'max:7'], 'days.*' => ['integer', 'between:1,7', 'distinct'], 'note' => ['nullable', 'string', 'max:140']];
        if ($profile) {
            $rules += ['notify' => ['required', 'boolean'], 'available' => ['required', 'boolean'], 'intentions' => ['array', 'max:3'], 'intentions.*' => ['string', Rule::in(array_keys(self::INTENTIONS)), 'distinct'], 'terms' => ['array', 'max:3'], 'terms.*' => ['string', Rule::in(array_keys(self::TERMS)), 'distinct'], 'zones' => ['array', 'max:5'], 'zones.*' => ['uuid', Rule::exists('cities', 'id')->when((bool) ($data['enabled'] ?? false), fn ($rule) => $rule->where('active', true)), 'distinct']];
        } else {
            $rules += ['open' => ['required', 'boolean'], 'activity' => ['required', Rule::in(['service', 'training'])], 'terms' => ['required', Rule::in(array_keys(self::TERMS))], 'budget' => ['nullable', 'string', 'max:100']];
        }
        $nested = [$root => ['array:'.implode(',', array_keys($defaults))]];
        foreach ($rules as $key => $rule) {
            $nested[$root.'.'.$key] = $rule;
        }
        $clean = Validator::make([$root => array_replace($defaults, $data)], $nested)->validate()[$root];
        if ($clean['from'] && $clean['until'] && $clean['until'] < $clean['from']) {
            throw ValidationException::withMessages([$root.'.until' => 'La fin doit être postérieure ou égale au début.']);
        }
        foreach (['note', 'budget'] as $key) {
            if (array_key_exists($key, $clean)) {
                $clean[$key] = Normalizer::multiline((string) $clean[$key]);
            }
        }
        foreach (['from', 'until'] as $key) {
            $clean[$key] = (string) ($clean[$key] ?? '');
        }
        $clean['days'] = array_values(array_map('intval', $clean['days']));
        if ($profile) {
            $clean['zones'] = array_values($clean['zones']);
            $clean['intentions'] = array_values($clean['intentions']);
            $clean['terms'] = array_values($clean['terms']);
        }

        return $clean;
    }
}
