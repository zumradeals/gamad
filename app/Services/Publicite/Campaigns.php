<?php

namespace App\Services\Publicite;

use App\Enums\AdCta;
use App\Enums\AdObjective;
use App\Enums\AdState;
use App\Enums\NotificationKind;
use App\Exceptions\AdException;
use App\Models\AdCampaign;
use App\Models\AdModeration;
use App\Models\Company;
use App\Models\User;
use App\Services\AdminJournal;
use App\Services\Notifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cycle de vie d'une campagne (D-212). Les autorisations (qui) sont vérifiées par la Policy avant d'appeler ce service ; ici vivent les
 * règles d'état (quoi, quand) et de contenu :
 *
 *   Brouillon ou Refusée → (envoi) → En examen → (modération) → Prête ou Refusée
 *   Prête, En pause ou Active : toute modification la renvoie En examen (nouvelle modération)
 *   Active ↔ En pause ; Arrêter (Prête, Active, En pause) → Terminée, définitif ; un Brouillon se supprime.
 *
 * Aucun paiement : le budget en FCFA est prévisionnel.
 */
class Campaigns
{
    public const MAX_DAYS = 90;

    public const MIN_BUDGET = 1000;

    public const MAX_BUDGET = 10_000_000;

    public function __construct(private readonly AudienceEstimator $audience, private readonly Notifier $notifier) {}

    /**
     * Crée un brouillon, ou modifie une campagne. Une campagne Prête, En pause ou Active modifiée repart En examen, et doit alors être
     * complète. Une campagne En examen ou Terminée n'est pas modifiable (la personne la retire d'abord).
     *
     * @param  array<string, mixed>  $data
     */
    public function save(User $user, ?AdCampaign $campaign, array $data): AdCampaign
    {
        $clean = $this->validated($user, $data);

        return DB::transaction(function () use ($user, $campaign, $clean) {
            if ($campaign === null) {
                $this->throttleCreation($user);
                $campaign = new AdCampaign;
                $campaign->user_id = $user->getKey();
                $campaign->state = AdState::Draft->value;
            } else {
                $campaign = AdCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
                $state = AdState::from($campaign->state);
                if (! in_array($state, [AdState::Draft, AdState::Refused, AdState::Ready, AdState::Paused], true) || $campaign->displayState() === AdState::Done) {
                    throw AdException::notEditable();
                }
            }

            $previous = $campaign->exists ? AdState::from($campaign->state) : null;
            $campaign->fill($clean['attributes']);

            if (in_array($previous, [AdState::Ready, AdState::Paused], true)) {
                $this->assertSubmittable($user, $campaign, $clean);
                $campaign->state = AdState::Review->value;
                $campaign->submitted_at = now();
                $campaign->refusal_reason = null;
            } elseif ($previous === AdState::Refused) {
                $campaign->state = AdState::Draft->value;
            }

            $campaign->save();
            $this->syncAudience($campaign, $clean);

            return $campaign->refresh();
        });
    }

    /** Envoie une campagne à la modération : complète, annonceur valide, audience suffisante. */
    public function submit(User $user, AdCampaign $campaign): AdCampaign
    {
        return DB::transaction(function () use ($user, $campaign) {
            $campaign = AdCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            if (! in_array(AdState::from($campaign->state), [AdState::Draft, AdState::Refused], true)) {
                throw AdException::wrongState('envoyer');
            }
            $this->throttleSubmission($user);
            $this->assertSubmittable($user, $campaign, [
                'skills' => $campaign->skills()->pluck('skills.id')->all(),
                'domains' => $campaign->domains()->pluck('domains.id')->all(),
                'cities' => $campaign->cities()->pluck('cities.id')->all(),
            ]);
            $campaign->forceFill(['state' => AdState::Review->value, 'submitted_at' => now(), 'refusal_reason' => null])->save();

            return $campaign->refresh();
        });
    }

    /** Retirer : En examen → Brouillon. */
    public function withdraw(AdCampaign $campaign): AdCampaign
    {
        return $this->transition($campaign, [AdState::Review], 'retirer', fn (AdCampaign $c) => $c->forceFill(['state' => AdState::Draft->value, 'submitted_at' => null]));
    }

    /** Pause : seulement une campagne Active. */
    public function pause(AdCampaign $campaign): AdCampaign
    {
        return $this->transition($campaign, [AdState::Active], 'mettre en pause', fn (AdCampaign $c) => $c->forceFill(['state' => AdState::Paused->value]));
    }

    /** Reprendre : En pause → Prête (Active si elle est en cours de diffusion). */
    public function resume(AdCampaign $campaign): AdCampaign
    {
        return $this->transition($campaign, [AdState::Paused], 'reprendre', fn (AdCampaign $c) => $c->forceFill(['state' => AdState::Ready->value]));
    }

    /** Arrêter : définitif. Prête, Active ou En pause → Terminée. */
    public function stop(AdCampaign $campaign): AdCampaign
    {
        return $this->transition($campaign, [AdState::Ready, AdState::Active, AdState::Paused], 'arrêter', fn (AdCampaign $c) => $c->forceFill(['state' => AdState::Done->value, 'stopped_at' => now()]));
    }

    /** Supprimer : un brouillon seulement. */
    public function delete(AdCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $campaign = AdCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            if ($campaign->state !== AdState::Draft->value) {
                throw AdException::wrongState('supprimer');
            }
            $campaign->delete();
        });
    }

    /** Dupliquer : un nouveau brouillon avec le même contenu et la même audience, sans dates ni état hérités. */
    public function duplicate(User $user, AdCampaign $campaign): AdCampaign
    {
        return DB::transaction(function () use ($user, $campaign) {
            $this->throttleCreation($user);
            $copy = $campaign->replicate(['state', 'refusal_reason', 'submitted_at', 'moderated_at', 'stopped_at', 'starts_on', 'ends_on']);
            $copy->user_id = $user->getKey();
            $copy->state = AdState::Draft->value;
            $copy->save();
            $copy->skills()->sync($campaign->skills()->pluck('skills.id')->all());
            $copy->domains()->sync($campaign->domains()->pluck('domains.id')->all());
            $copy->cities()->sync($campaign->cities()->pluck('cities.id')->all());

            return $copy;
        });
    }

    /** Décision de la modération : acceptée (Prête) ou refusée (motif obligatoire). Au journal d'administration. */
    public function moderate(User $moderator, AdCampaign $campaign, bool $accept, ?string $reason = null): AdCampaign
    {
        Gate::forUser($moderator)->authorize('moderate', $campaign);
        $reason = $reason === null ? null : trim($reason);
        if (! $accept && (mb_strlen((string) $reason) < 5 || mb_strlen($reason) > 280)) {
            throw ValidationException::withMessages(['reason' => 'Indiquez le motif du refus (5 à 280 caractères).']);
        }

        return DB::transaction(function () use ($moderator, $campaign, $accept, $reason) {
            $campaign = AdCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            if ($campaign->state !== AdState::Review->value) {
                throw AdException::wrongState('modérer');
            }
            $campaign->forceFill([
                'state' => $accept ? AdState::Ready->value : AdState::Refused->value,
                'refusal_reason' => $accept ? null : $reason,
                'moderated_at' => now(),
            ])->save();
            AdModeration::query()->create(['ad_campaign_id' => $campaign->getKey(), 'reviewer_id' => $moderator->getKey(), 'decision' => $accept ? 'accepted' : 'refused', 'reason' => $accept ? null : $reason]);
            $this->notifier->push($campaign->owner, $accept ? NotificationKind::AdAccepted : NotificationKind::AdRefused, $moderator);
            AdminJournal::record($moderator, $accept ? 'ad.accepted' : 'ad.refused', $campaign, $accept ? null : $reason, ['annonce' => (string) $campaign->title]);

            return $campaign->refresh();
        });
    }

    /**
     * Retirer de la diffusion (D-211) : la modération retire une annonce déjà acceptée, signalée par des personnes, avec un motif obligatoire.
     * La campagne devient Refusée (l'annonceur lit le motif, corrige et renvoie) ; la décision est au journal et notifiée.
     */
    public function revoke(User $moderator, AdCampaign $campaign, string $reason): AdCampaign
    {
        Gate::forUser($moderator)->authorize('moderate', $campaign);
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 280) {
            throw ValidationException::withMessages(['reason' => 'Indiquez le motif du retrait (5 à 280 caractères).']);
        }

        return DB::transaction(function () use ($moderator, $campaign, $reason) {
            $campaign = AdCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            if (! in_array($campaign->state, [AdState::Ready->value, AdState::Paused->value], true)) {
                throw AdException::wrongState('retirer');
            }
            $campaign->forceFill(['state' => AdState::Refused->value, 'refusal_reason' => $reason, 'moderated_at' => now()])->save();
            AdModeration::query()->create(['ad_campaign_id' => $campaign->getKey(), 'reviewer_id' => $moderator->getKey(), 'decision' => 'refused', 'reason' => $reason]);
            $this->notifier->push($campaign->owner, NotificationKind::AdRefused, $moderator);
            AdminJournal::record($moderator, 'ad.revoked', $campaign, $reason, ['annonce' => (string) $campaign->title]);

            return $campaign->refresh();
        });
    }

    /** Les annonceurs possibles de la personne : elle-même, et ses entreprises vérifiées. @return array{person: User, companies: \Illuminate\Support\Collection<int, Company>} */
    public function advertisers(User $user): array
    {
        return ['person' => $user, 'companies' => Company::query()->where('owner_id', $user->getKey())->publiclyVisible()->orderBy('name')->get()];
    }

    /** @param  array<int, AdState>  $from */
    private function transition(AdCampaign $campaign, array $from, string $action, \Closure $apply): AdCampaign
    {
        return DB::transaction(function () use ($campaign, $from, $action, $apply) {
            $campaign = AdCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            if (! in_array($campaign->displayState(), $from, true)) {
                throw AdException::wrongState($action);
            }
            $apply($campaign);
            $campaign->save();

            return $campaign->refresh();
        });
    }

    /**
     * Règles de contenu des champs saisis (brouillon compris) : bornes, texte simple sans lien, une destination en https, listes bornées.
     *
     * @param  array<string, mixed>  $data
     * @return array{attributes: array<string, mixed>, skills: array<int, string>, domains: array<int, string>, cities: array<int, string>}
     */
    public function validated(User $user, array $data): array
    {
        $noLink = fn (string $attribute, mixed $value, \Closure $fail) => preg_match('~(https?:|www\.|\.(com|net|org|fr|ci)\b)~i', (string) $value)
            ? $fail('Écrivez un texte simple, sans lien : la page de destination se règle à part.') : null;

        $input = Validator::make($data, [
            'company_id' => ['nullable', 'uuid'],
            'objective' => ['nullable', Rule::enum(AdObjective::class)],
            'cta' => ['nullable', Rule::enum(AdCta::class)],
            'title' => ['nullable', 'string', 'max:40', $noLink],
            'body' => ['nullable', 'string', 'max:180', $noLink],
            'image_path' => ['nullable', 'string', 'max:255'],
            'destination_url' => ['nullable', 'string', 'max:500', 'url:https'],
            'audience_mode' => ['required', Rule::in(['personalized', 'contextual'])],
            'remote' => ['boolean'],
            'budget_fcfa' => ['nullable', 'integer', 'between:0,'.self::MAX_BUDGET],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'skills' => ['array', 'max:8'], 'skills.*' => ['uuid', 'exists:skills,id'],
            'domains' => ['array', 'max:8'], 'domains.*' => ['uuid', 'exists:domains,id'],
            'cities' => ['array', 'max:5'], 'cities.*' => ['uuid', 'exists:cities,id'],
        ], [
            'title.max' => 'Le titre fait 40 caractères au maximum.', 'body.max' => 'Le texte fait 180 caractères au maximum.',
            'destination_url.url' => 'La page de destination doit être une adresse en https.',
            'ends_on.after_or_equal' => 'La date de fin doit suivre la date de début.',
            'skills.max' => 'Huit compétences au plus.', 'domains.max' => 'Huit domaines au plus.', 'cities.max' => 'Cinq lieux au plus.',
        ])->validate();

        if (! empty($input['company_id'])) {
            $company = Company::query()->find($input['company_id']);
            if ($company === null || $company->owner_id !== $user->getKey() || ! $company->verified()) {
                throw AdException::advertiser();
            }
        }
        if (isset($input['starts_on'], $input['ends_on']) && (new Carbon($input['starts_on']))->diffInDays(new Carbon($input['ends_on'])) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['ends_on' => 'Une campagne dure '.self::MAX_DAYS.' jours au plus.']);
        }

        $personalized = ($input['audience_mode'] ?? 'personalized') === 'personalized';
        $attributes = array_intersect_key($input, array_flip(['company_id', 'objective', 'cta', 'title', 'body', 'image_path', 'destination_url', 'audience_mode', 'remote', 'budget_fcfa', 'starts_on', 'ends_on']));
        $attributes['company_id'] = $attributes['company_id'] ?? null;
        $attributes['remote'] = $personalized && ! empty($input['remote']);
        foreach (['title', 'body', 'destination_url'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = ($attributes[$key] = trim((string) $attributes[$key])) === '' ? null : $attributes[$key];
            }
        }

        // Le mode contextuel ne cible aucune personne : aucun critère n'est conservé.
        return [
            'attributes' => $attributes,
            'skills' => $personalized ? array_values(array_unique($input['skills'] ?? [])) : [],
            'domains' => $personalized ? array_values(array_unique($input['domains'] ?? [])) : [],
            'cities' => $personalized ? array_values(array_unique($input['cities'] ?? [])) : [],
        ];
    }

    /**
     * Tout ce qui est exigé avant la modération : contenu complet, annonceur valide, dates à venir, budget minimal, audience suffisante.
     *
     * @param  array<string, mixed>  $clean
     */
    private function assertSubmittable(User $user, AdCampaign $campaign, array $clean): void
    {
        $errors = [];
        $objective = $campaign->objective;
        if ($objective === null) {
            $errors['objective'] = 'Choisissez un objectif.';
        }
        if (blank($campaign->title)) {
            $errors['title'] = 'Écrivez le titre de l’annonce.';
        }
        if (blank($campaign->body)) {
            $errors['body'] = 'Écrivez le texte de l’annonce.';
        }
        if ($campaign->cta === null || ($objective !== null && ! in_array($campaign->cta, $objective->ctas(), true))) {
            $errors['cta'] = 'Choisissez un bouton adapté à votre objectif.';
        } elseif ($campaign->cta === AdCta::Link && blank($campaign->destination_url)) {
            $errors['destination_url'] = 'Indiquez la page de destination (adresse en https).';
        }
        $today = now('Africa/Abidjan')->startOfDay();
        if ($campaign->starts_on === null || $campaign->ends_on === null) {
            $errors['starts_on'] = 'Choisissez les dates de début et de fin.';
        } elseif ($campaign->starts_on->lt($today)) {
            $errors['starts_on'] = 'La date de début ne peut pas être passée.';
        }
        if ($campaign->budget_fcfa === null || $campaign->budget_fcfa < self::MIN_BUDGET) {
            $errors['budget_fcfa'] = 'Le budget prévisionnel est d’au moins '.number_format(self::MIN_BUDGET, 0, ',', ' ').' FCFA.';
        }
        if ($campaign->company_id !== null) {
            $company = Company::query()->find($campaign->company_id);
            if ($company === null || $company->owner_id !== $user->getKey() || ! $company->verified()) {
                $errors['company_id'] = AdException::advertiser()->getMessage();
            }
        }
        if ($campaign->isPersonalized()) {
            $count = $this->audience->count($user, $clean['skills'] ?? [], $clean['domains'] ?? [], $clean['cities'] ?? [], (bool) $campaign->remote);
            if ($this->audience->level($count) === AudienceEstimator::TOO_SMALL) {
                $errors['audience'] = 'Votre audience est trop restreinte pour protéger les personnes : élargissez vos critères ou choisissez la diffusion contextuelle.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param  array<string, mixed>  $clean */
    private function syncAudience(AdCampaign $campaign, array $clean): void
    {
        $campaign->skills()->sync($clean['skills']);
        $campaign->domains()->sync($clean['domains']);
        $campaign->cities()->sync($clean['cities']);
    }

    private function throttleCreation(User $user): void
    {
        $count = AdCampaign::query()->where('user_id', $user->getKey())->where('created_at', '>=', now()->subDay())->count();
        if ($count >= (int) config('gamad.limits.ad_campaigns_per_day')) {
            throw AdException::tooMany();
        }
    }

    private function throttleSubmission(User $user): void
    {
        $count = AdCampaign::query()->where('user_id', $user->getKey())->whereNotNull('submitted_at')->where('submitted_at', '>=', now()->subDay())->count();
        if ($count >= (int) config('gamad.limits.ad_submissions_per_day')) {
            throw AdException::tooMany();
        }
    }
}
