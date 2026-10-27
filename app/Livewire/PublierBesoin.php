<?php

namespace App\Livewire;

use App\Enums\BeneficiaryKind;
use App\Enums\HelpKind;
use App\Livewire\Concerns\GuardsContributionsModule;
use App\Models\Company;
use App\Models\Domain;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Publication;
use App\Models\Zumra;
use App\Services\ContributionAccess;
use App\Services\ContributionNeeds;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Publier un besoin » (C1, D-207), en quatre questions : de quoi avez-vous besoin, quel type d'aide, pour qui, quelques précisions.
 * Localité, échéance et compétences sont facultatives ; ni budget ni document. L'audience est dite avant l'envoi. Le droit de publier
 * au nom d'un bénéficiaire est revérifié par le service ContributionNeeds, jamais seulement par la liste affichée ici.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Publier un besoin')]
class PublierBesoin extends Component
{
    use GuardsContributionsModule;

    public string $title = '';

    public string $kind = '';

    #[Url(as: 'pour', except: 'person')]
    public string $beneficiary = 'person';

    public string $details = '';

    public string $visibility = Publication::PUBLIC;

    public string $domain_id = '';

    public string $city_id = '';

    public string $needed_by = '';

    public string $skills = '';

    public function save(ContributionNeeds $needs)
    {
        $this->resetErrorBag();

        [$type, $id] = array_pad(explode(':', $this->beneficiary, 2), 2, null);

        $need = $needs->publish(Auth::user(), [
            'title' => $this->title,
            'kind' => $this->kind,
            'details' => $this->details,
            'beneficiary' => [$type, $id],
            'visibility' => $this->visibility,
            'domain_id' => $this->domain_id,
            'city_id' => $this->city_id,
            'needed_by' => $this->needed_by,
            'skills' => array_values(array_filter(array_map('trim', explode(',', $this->skills)), fn ($skill) => $skill !== '')),
        ]);

        session()->flash('status', 'Votre besoin est publié. Vous serez prévenu dès qu’une personne vous proposera son aide.');

        return $this->redirect(route('publications.show', $need), navigate: false);
    }

    public function render()
    {
        $options = $this->options();
        [$type] = array_pad(explode(':', $this->beneficiary, 2), 2, null);

        // L'audience possible dépend du bénéficiaire : une entreprise publie en public ; une ZUMRA peut réserver à ses membres.
        $audiences = match (BeneficiaryKind::tryFrom((string) $type)) {
            BeneficiaryKind::Zumra => [Publication::PUBLIC => 'Public : visible par tout le monde, même sans compte', Publication::MEMBERS => 'Membres de GAMAD : personnes connectées seulement', Publication::ZUMRA => 'Membres de la ZUMRA seulement'],
            BeneficiaryKind::Company => [Publication::PUBLIC => 'Public : visible par tout le monde, même sans compte'],
            default => [Publication::PUBLIC => 'Public : visible par tout le monde, même sans compte', Publication::MEMBERS => 'Membres de GAMAD : personnes connectées seulement'],
        };

        return view('livewire.publier-besoin', [
            'options' => $options,
            'audiences' => $audiences,
            'kinds' => HelpKind::cases(),
            'domains' => Domain::query()->where('active', true)->ordered()->get(),
        ]);
    }

    /**
     * Les bénéficiaires pour lesquels cette personne peut publier : elle-même, puis ce qu'elle représente.
     *
     * @return array<string, string>
     */
    private function options(): array
    {
        $user = Auth::user();
        $access = app(ContributionAccess::class);
        $options = ['person' => 'Moi-même'];

        if ($access->represents($user, BeneficiaryKind::Gamad, null)) {
            $options['gamad'] = 'GAMAD (besoin officiel)';
        }

        foreach (Zumra::query()->whereIn('id', $user->managedZumraIds())->orderBy('name_key')->get() as $zumra) {
            if ($access->represents($user, BeneficiaryKind::Zumra, $zumra)) {
                $options['zumra:'.$zumra->id] = 'ZUMRA · '.$zumra->name;
            }
        }

        $managedProjects = Project::query()->showcased()->where(fn (Builder $q) => $q->where('owner_id', $user->getKey())
            ->orWhereIn('id', ProjectMember::query()->where('user_id', $user->getKey())->where('manages_contributions', true)->select('project_id')))->get();

        foreach ($managedProjects as $project) {
            if ($access->represents($user, BeneficiaryKind::Project, $project)) {
                $options['project:'.$project->id] = 'Projet · '.$project->name;
            }
        }

        foreach (Company::query()->publiclyVisible()->where('owner_id', $user->getKey())->orderBy('name')->get() as $company) {
            $options['company:'.$company->id] = 'Entreprise · '.$company->name;
        }

        // Une valeur choisie qui n'est pas permise est ramenée à « moi-même » ; le service refuse de toute façon.
        if (! array_key_exists($this->beneficiary, $options) && ! Str::startsWith($this->beneficiary, ['project:', 'zumra:', 'company:'])) {
            $this->beneficiary = 'person';
        }

        return $options;
    }
}
