<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\Domain;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Support\Normalizer;
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

        $bio = $this->cleanBio((string) ($data['bio'] ?? ''));

        if (mb_strlen($bio) > Profile::BIO_MAX) {
            throw ValidationException::withMessages(['bio' => 'La présentation ne peut pas dépasser '.Profile::BIO_MAX.' caractères.']);
        }

        if (! in_array($data['visibility'], [Profile::PUBLIC, Profile::MEMBERS], true)) {
            throw ValidationException::withMessages(['visibility' => 'Choisissez qui voit votre profil.']);
        }

        $offered = $this->skillNames($data['offered'], 'offered');
        $wanted = $this->skillNames($data['wanted'], 'wanted');
        $domainIds = array_values(array_unique($data['domain_ids']));

        if (count($domainIds) > Profile::MAX_DOMAINS) {
            throw ValidationException::withMessages(['domain_ids' => 'Trois domaines au plus.']);
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
            DB::transaction(function () use ($user, $name, $bio, $data, $offered, $wanted, $domainIds, $newPhotoPath, $removePhoto, &$oldPhotoPath, &$finalPhotoPath) {
                /** @var Profile $profile */
                $profile = Profile::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrFail();

                [$countryId, $cityId] = $this->territory($profile, $data['country_id'] ?? null, $data['city_id'] ?? null);
                $this->checkDomains($profile, $domainIds);

                DisplayName::change($user, $name);

                $oldPhotoPath = $profile->photo_path;
                $finalPhotoPath = $newPhotoPath ?? ($removePhoto ? null : $oldPhotoPath);

                $profile->update([
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

        if ($oldPhotoPath !== null && $oldPhotoPath !== $finalPhotoPath) {
            Storage::disk(Profile::PHOTO_DISK)->delete($oldPhotoPath);
        }
    }

    /** Présentation nettoyée : retours à la ligne unifiés, espaces superflus et caractères invisibles retirés. */
    private function cleanBio(string $bio): string
    {
        $bio = str_replace(["\r\n", "\r"], "\n", $bio);
        $bio = preg_replace('/[^\P{Cc}\n]|\p{Cf}/u', '', $bio) ?? $bio;
        $bio = preg_replace('/[^\S\n]+/u', ' ', $bio) ?? $bio;
        $bio = preg_replace('/ ?\n ?/', "\n", $bio) ?? $bio;

        return trim(preg_replace("/\n{3,}/", "\n\n", $bio) ?? $bio);
    }

    /**
     * Noms de capacités nettoyés, sans doublon (au sens de D-066), validés (2 à 60 caractères, au moins une lettre ou un
     * chiffre) et limités à 10.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private function skillNames(array $names, string $list): array
    {
        $clean = [];

        foreach ($names as $name) {
            $display = Normalizer::display((string) $name);

            if ($display === '') {
                continue;
            }

            $length = mb_strlen($display);

            if ($length < Skill::MIN_LENGTH || $length > Skill::MAX_LENGTH || ! preg_match('/[\p{L}\p{N}]/u', $display)) {
                throw ValidationException::withMessages([$list => 'Chaque capacité comporte de '.Skill::MIN_LENGTH.' à '.Skill::MAX_LENGTH.' caractères, dont au moins une lettre ou un chiffre.']);
            }

            $clean[Normalizer::key($display)] ??= $display;
        }

        if (count($clean) > Profile::MAX_SKILLS_PER_KIND) {
            throw ValidationException::withMessages([$list => 'Dix capacités au plus dans cette liste.']);
        }

        return array_values($clean);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function territory(Profile $profile, ?string $countryId, ?string $cityId): array
    {
        $countryId = filled($countryId) ? $countryId : null;
        $cityId = filled($cityId) ? $cityId : null;

        if ($countryId !== null) {
            $country = Country::query()->find($countryId);

            if (! $country || (! $country->active && $country->id !== $profile->country_id)) {
                throw ValidationException::withMessages(['country_id' => 'Choisissez un pays dans la liste.']);
            }
        }

        if ($cityId !== null) {
            $city = City::query()->find($cityId);

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
