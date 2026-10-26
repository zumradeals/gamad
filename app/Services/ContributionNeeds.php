<?php

namespace App\Services;

use App\Enums\BeneficiaryKind;
use App\Enums\HelpKind;
use App\Enums\NeedState;
use App\Enums\PublicationType;
use App\Models\City;
use App\Models\Company;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Skill;
use App\Models\User;
use App\Models\Zumra;
use App\Support\Normalizer;
use App\Support\SkillList;
use App\Support\ZumraModule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Publier un besoin au nom d'une personne, d'une ZUMRA, d'un projet, d'une entreprise ou de GAMAD (C1, D-207). Un besoin reste une
 * publication « J'ai besoin » : on y gagne un titre, une forme d'aide, une échéance et, pour GAMAD, le marquage officiel. Le besoin
 * d'un projet privé n'existe pas : le projet doit d'abord avoir publié sa présentation (P1). Aucun budget ni document n'est demandé.
 */
class ContributionNeeds
{
    public const TITLE_MIN = 3;

    public const TITLE_MAX = 80;

    public function __construct(private readonly ContributionAccess $access) {}

    /**
     * @param  array{title?: ?string, details?: ?string, kind?: ?string, beneficiary?: array{0: string, 1: ?string}, visibility?: ?string, domain_id?: ?string, city_id?: ?string, needed_by?: ?string, skills?: array<int, string>}  $data
     *
     * @throws ValidationException
     */
    public function publish(User $author, array $data): Publication
    {
        if (! $author->isActive() || ! $this->access->moduleOpen($author)) {
            abort(404);
        }

        $kind = HelpKind::tryFrom((string) ($data['kind'] ?? ''));

        if (! $kind) {
            throw ValidationException::withMessages(['kind' => 'Choisissez le type d’aide que vous recherchez.']);
        }

        $title = Normalizer::display((string) ($data['title'] ?? ''));

        if (mb_strlen($title) < self::TITLE_MIN) {
            throw ValidationException::withMessages(['title' => 'Dites en quelques mots de quoi vous avez besoin.']);
        }

        if (mb_strlen($title) > self::TITLE_MAX) {
            throw ValidationException::withMessages(['title' => 'Un titre ne peut pas dépasser '.self::TITLE_MAX.' caractères.']);
        }

        $details = Normalizer::multiline((string) ($data['details'] ?? ''));

        if (mb_strlen($details) > Publication::BODY_MAX) {
            throw ValidationException::withMessages(['details' => 'Les précisions ne peuvent pas dépasser '.Publication::BODY_MAX.' caractères.']);
        }

        [$beneficiaryKind, $beneficiary] = $this->beneficiary($author, $data['beneficiary'] ?? []);

        if ($kind === HelpKind::Financial && $beneficiaryKind === BeneficiaryKind::Company) {
            throw ValidationException::withMessages(['kind' => 'Une entreprise ne publie pas de besoin financier dans GAMAD : choisissez compétences, temps ou matériel.']);
        }

        $visibility = $data['visibility'] ?? Publication::PUBLIC;
        $allowed = match ($beneficiaryKind) {
            BeneficiaryKind::Zumra => [Publication::PUBLIC, Publication::MEMBERS, Publication::ZUMRA],
            BeneficiaryKind::Company => [Publication::PUBLIC],
            default => [Publication::PUBLIC, Publication::MEMBERS],
        };

        if (! in_array($visibility, $allowed, true)) {
            throw ValidationException::withMessages(['visibility' => 'Choisissez qui peut voir ce besoin.']);
        }

        $domainId = $this->activeId(Domain::class, $data['domain_id'] ?? null, 'domain_id', 'Choisissez un domaine dans la liste.');
        $cityId = $this->activeId(City::class, $data['city_id'] ?? null, 'city_id', 'Choisissez une localité dans la liste.');
        $skills = SkillList::clean($data['skills'] ?? [], 'skills', Publication::MAX_SKILLS, 'Trois compétences au plus.');
        $neededBy = $this->date($data['needed_by'] ?? null);

        $this->throttle($author);

        return DB::transaction(function () use ($author, $kind, $title, $details, $beneficiaryKind, $beneficiary, $visibility, $domainId, $cityId, $skills, $neededBy) {
            $publication = new Publication([
                'type' => PublicationType::Need,
                'title' => $title,
                // Le texte publié reste obligatoire côté Fil : sans précisions, le titre en tient lieu.
                'body' => $details === '' ? $title : $details,
                'help_kind' => $kind->value,
                'needed_by' => $neededBy,
                'visibility' => $visibility,
                'domain_id' => $domainId,
                'city_id' => $cityId,
                'need_state' => NeedState::Open,
                'official' => $beneficiaryKind === BeneficiaryKind::Gamad,
            ]);
            $publication->author()->associate($author);

            match ($beneficiaryKind) {
                BeneficiaryKind::Zumra => $publication->zumra()->associate($beneficiary),
                // Le besoin d'un projet porte aussi la ZUMRA d'accueil, sans lui donner aucun pouvoir sur lui.
                BeneficiaryKind::Project => $publication->project()->associate($beneficiary)->zumra()->associate($beneficiary->zumra),
                BeneficiaryKind::Company => $publication->company()->associate($beneficiary),
                default => null,
            };
            $publication->save();

            $publication->skills()->attach(collect($skills)->mapWithKeys(
                fn (string $name, int $position) => [Skill::findOrCreateByName($name)->id => ['position' => $position]]
            )->all());

            return $publication;
        });
    }

    /**
     * Pour qui l'on publie, et le droit de le faire : la personne elle-même, un représentant de la ZUMRA, du projet ou de l'entreprise,
     * ou le super administrateur pour GAMAD.
     *
     * @param  array{0?: string, 1?: ?string}  $target
     * @return array{0: BeneficiaryKind, 1: ?Model}
     */
    private function beneficiary(User $author, array $target): array
    {
        $kind = BeneficiaryKind::tryFrom((string) ($target[0] ?? BeneficiaryKind::Person->value));
        $id = $target[1] ?? null;

        if (! $kind) {
            throw ValidationException::withMessages(['beneficiary' => 'Choisissez pour qui est ce besoin.']);
        }

        if (! in_array($kind, [BeneficiaryKind::Gamad, BeneficiaryKind::Person], true) && (! is_string($id) || ! Str::isUuid($id))) {
            throw ValidationException::withMessages(['beneficiary' => 'Choisissez pour qui est ce besoin.']);
        }

        $beneficiary = match ($kind) {
            BeneficiaryKind::Gamad => null,
            BeneficiaryKind::Person => $author,
            BeneficiaryKind::Zumra => Zumra::query()->find($id),
            BeneficiaryKind::Project => Project::query()->find($id),
            BeneficiaryKind::Company => Company::query()->find($id),
        };

        // Le droit de publier au nom d'un bénéficiaire se vérifie ici, jamais seulement dans la liste proposée à l'écran.
        abort_unless($this->access->represents($author, $kind, $beneficiary), 403);

        if ($kind === BeneficiaryKind::Zumra && ! ZumraModule::accessibleTo($author)) {
            abort(404);
        }

        if ($kind === BeneficiaryKind::Project && ! $beneficiary->isShowcasePublished()) {
            throw ValidationException::withMessages(['beneficiary' => 'Ce projet est privé : publiez d’abord sa présentation publique pour demander de l’aide.']);
        }

        return [$kind, $beneficiary];
    }

    private function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', (string) $value, 'Africa/Abidjan')->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['needed_by' => 'Choisissez une date valide.']);
        }

        if ($date->isBefore(now('Africa/Abidjan')->startOfDay()) || $date->isAfter(now('Africa/Abidjan')->addYears(2))) {
            throw ValidationException::withMessages(['needed_by' => 'Choisissez une date à venir, dans les deux ans.']);
        }

        return $date->toDateString();
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function activeId(string $model, mixed $id, string $field, string $message): ?string
    {
        if (blank($id)) {
            return null;
        }

        if (! is_string($id) || ! Str::isUuid($id) || ! $model::query()->whereKey($id)->where('active', true)->exists()) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $id;
    }

    private function throttle(User $author): void
    {
        $key = "contribution-need:{$author->getKey()}";

        if (RateLimiter::tooManyAttempts($key, config('gamad.limits.contribution_needs_per_day'))) {
            throw ValidationException::withMessages(['title' => 'Vous avez atteint la limite de besoins pour aujourd’hui.']);
        }

        RateLimiter::hit($key, 86400);
    }
}
