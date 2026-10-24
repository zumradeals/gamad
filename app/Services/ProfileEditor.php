<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\Domain;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Support\Normalizer;
use App\Support\SkillList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Enregistrement du profil d'une personne. Toutes les règles du Lot 1 sont tenues ici, côté serveur, quel que soit
 * l'écran qui appelle (CLAUDE.md, règle 4) : 280 caractères de présentation, 2 à 60 caractères par capacité, 10 capacités
 * au plus par sens, 3 domaines au plus, ville du pays choisi, visibilité, photo.
 *
 * Le profil est verrouillé pendant l'enregistrement : deux enregistrements simultanés de la même personne ne peuvent pas
 * dépasser les limites.
 */
class ProfileEditor
{
    /**
     * @param  array{name: string, bio: ?string, country_id: ?string, city_id: ?string, visibility: string, offered: array<int, string>, wanted: array<int, string>, domain_ids: array<int, string>}  $data
     * @param  string|null  $photo  octets de la nouvelle photo, ou null pour ne pas y toucher
     *
     * @throws ValidationException
     */
    public function save(User $user, array $data, ?string $photo = null, bool $removePhoto = false): void
    {
        $name = trim($data['name']);

        if ($name === '' || mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['name' => 'Le nom affiché doit comporter de 1 à 255 caractères.']);
        }

        $bio = Normalizer::multiline((string) ($data['bio'] ?? ''));

        if (mb_strlen($bio) > Profile::BIO_MAX) {
            throw ValidationException::withMessages(['bio' => 'La présentation ne peut pas dépasser '.Profile::BIO_MAX.' caractères.']);
        }

        if (! in_array($data['visibility'], [Profile::PUBLIC, Profile::MEMBERS], true)) {
            throw ValidationException::withMessages(['visibility' => 'Choisissez qui voit votre profil.']);
        }

        $offered = SkillList::clean($data['offered'], 'offered', Profile::MAX_SKILLS_PER_KIND, 'Dix capacités au plus dans cette liste.');
        $wanted = SkillList::clean($data['wanted'], 'wanted', Profile::MAX_SKILLS_PER_KIND, 'Dix capacités au plus dans cette liste.');
        $domainIds = array_values(array_unique($data['domain_ids']));

        if (collect($domainIds)->contains(fn ($id) => ! is_string($id) || ! Str::isUuid($id))) {
            throw ValidationException::withMessages(['domain_ids' => 'Choisissez des domaines dans la liste.']);
        }

        if (count($domainIds) > Profile::MAX_DOMAINS) {
            throw ValidationException::withMessages(['domain_ids' => 'Trois domaines au plus.']);
        }

        $details = app(ProfileDetails::class)->clean($user, $data, $offered);
        if (array_key_exists('matching_preferences', $data)) {
            $details['matching_preferences'] = app(MatchingConditions::class)->preferences($data['matching_preferences']);
        }

        $newPhotoPath = null;

        if ($photo !== null) {
            try {
                $jpeg = ProfilePhoto::encode($photo);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['photo' => $e->getMessage()]);
            }

            $newPhotoPath = 'profile-photos/'.Str::uuid().'.jpg';
            Storage::disk(Profile::PHOTO_DISK)->put($newPhotoPath, $jpeg);
        }

        $oldPhotoPath = null;
        $finalPhotoPath = null;

        try {
            DB::transaction(function () use ($user, $name, $bio, $data, $offered, $wanted, $domainIds, $details, $newPhotoPath, $removePhoto, &$oldPhotoPath, &$finalPhotoPath) {
                /** @var Profile $profile */
                $profile = Profile::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrFail();

                [$countryId, $cityId] = $this->territory($profile, $data['country_id'] ?? null, $data['city_id'] ?? null);
                $this->checkDomains($profile, $domainIds);

                DisplayName::change($user, $name);

                $oldPhotoPath = $profile->photo_path;
                $finalPhotoPath = $newPhotoPath ?? ($removePhoto ? null : $oldPhotoPath);

                $profile->update([
                    ...$details,
                    'bio' => $bio === '' ? null : $bio,
                    'country_id' => $countryId,
                    'city_id' => $cityId,
                    'visibility' => $data['visibility'],
                    'photo_path' => $finalPhotoPath,
                ]);

                $this->syncSkills($profile, $offered, $wanted);
                $profile->domains()->sync($domainIds);
            });
        } catch (\Throwable $e) {
            // L'enregistrement a échoué : la nouvelle photo, déjà écrite, ne doit pas rester orpheline.
            if ($newPhotoPath !== null) {
                Storage::disk(Profile::PHOTO_DISK)->delete($newPhotoPath);
            }

            throw $e;
        }

        if (array_key_exists('matching_preferences', $data)) {
            app(Matching::class)->profileChanged($user->id);
        }

        if ($oldPhotoPath !== null && $oldPhotoPath !== $finalPhotoPath) {
            Storage::disk(Profile::PHOTO_DISK)->delete($oldPhotoPath);
        }
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function territory(Profile $profile, ?string $countryId, ?string $cityId): array
    {
        $countryId = filled($countryId) ? $countryId : null;
        $cityId = filled($cityId) ? $cityId : null;

        if ($countryId !== null) {
            $country = Str::isUuid($countryId) ? Country::query()->find($countryId) : null;

            if (! $country || (! $country->active && $country->id !== $profile->country_id)) {
                throw ValidationException::withMessages(['country_id' => 'Choisissez un pays dans la liste.']);
            }
        }

        if ($cityId !== null) {
            $city = Str::isUuid($cityId) ? City::query()->find($cityId) : null;

            if (! $city || $countryId === null || $city->country_id !== $countryId || (! $city->active && $city->id !== $profile->city_id)) {
                throw ValidationException::withMessages(['city_id' => 'Choisissez une ville de ce pays, dans la liste.']);
            }
        }

        return [$countryId, $cityId];
    }

    /**
     * @param  array<int, string>  $domainIds
     */
    private function checkDomains(Profile $profile, array $domainIds): void
    {
        if ($domainIds === []) {
            return;
        }

        $current = $profile->domains()->pluck('domains.id')->all();
        $allowed = Domain::query()->whereIn('id', $domainIds)->get()
            ->filter(fn (Domain $domain) => $domain->active || in_array($domain->id, $current, true));

        if ($allowed->count() !== count($domainIds)) {
            throw ValidationException::withMessages(['domain_ids' => 'Choisissez des domaines dans la liste.']);
        }
    }

    /**
     * @param  array<int, string>  $offered
     * @param  array<int, string>  $wanted
     */
    private function syncSkills(Profile $profile, array $offered, array $wanted): void
    {
        DB::table('profile_skill')->where('profile_id', $profile->id)->delete();

        $rows = [];

        foreach (['offered' => $offered, 'wanted' => $wanted] as $kind => $names) {
            foreach ($names as $position => $name) {
                $rows[] = [
                    'profile_id' => $profile->id,
                    'skill_id' => Skill::findOrCreateByName($name)->id,
                    'kind' => $kind,
                    'position' => $position,
                ];
            }
        }

        DB::table('profile_skill')->insert($rows);
    }
}
