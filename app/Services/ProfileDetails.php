<?php

namespace App\Services;

use App\Models\Publication;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Présentation libre et expériences déclarées. Les preuves sont toujours des publications personnelles de l’auteur. */
class ProfileDetails
{
    public function clean(User $user, array $data, array $offered): array
    {
        $fields = array_intersect_key($data, array_flip(['introduction', 'experiences', 'skill_details', 'featured_publications']));
        $fields = Validator::make($fields, [
            'introduction' => ['sometimes', 'nullable', 'string', 'max:1200'],
            'experiences' => ['sometimes', 'array', 'max:10'],
            'experiences.*' => ['array:title,kind,period,description'],
            'experiences.*.title' => ['required', 'string', 'max:100'],
            'experiences.*.kind' => ['required', Rule::in(['personal', 'professional', 'volunteer', 'learning'])],
            'experiences.*.period' => ['nullable', 'string', 'max:80'],
            'experiences.*.description' => ['nullable', 'string', 'max:500'],
            'skill_details' => ['sometimes', 'array', 'max:10'],
            'skill_details.*' => ['array:skill,description,publication_id'],
            'skill_details.*.skill' => ['required', 'string', Rule::in($offered)],
            'skill_details.*.description' => ['required', 'string', 'max:280'],
            'skill_details.*.publication_id' => ['nullable', 'uuid'],
            'featured_publications' => ['sometimes', 'array', 'max:3'],
            'featured_publications.*' => ['required', 'uuid', 'distinct'],
        ])->validate();
        foreach (['experiences', 'skill_details'] as $key) {
            if (isset($fields[$key])) {
                $fields[$key] = array_values(array_map(fn ($row) => array_map(fn ($text) => is_string($text) ? Normalizer::multiline($text) : $text, $row), $fields[$key]));
            }
        }
        if (array_key_exists('introduction', $fields)) {
            $fields['introduction'] = Normalizer::multiline((string) $fields['introduction']) ?: null;
        }
        if (collect($fields['experiences'] ?? [])->contains(fn ($row) => $row['title'] === '') || collect($fields['skill_details'] ?? [])->contains(fn ($row) => $row['description'] === '')) {
            throw ValidationException::withMessages(['introduction' => 'Complétez les expériences et savoir-faire ajoutés, ou retirez-les.']);
        }
        $proofs = collect($fields['skill_details'] ?? [])->pluck('publication_id')->filter()->merge($fields['featured_publications'] ?? [])->unique();
        if ($proofs->isNotEmpty() && Publication::query()->where('author_id', $user->id)->whereNull('zumra_id')->whereNull('hidden_at')->whereIn('id', $proofs)->count() !== $proofs->count()) {
            throw ValidationException::withMessages(['featured_publications' => 'Choisissez vos propres publications personnelles encore visibles.']);
        }

        return $fields;
    }
}
