<?php

namespace App\Livewire;

use App\Enums\BeneficiaryKind;
use App\Enums\HelpKind;
use App\Enums\ProposalState;
use App\Livewire\Concerns\GuardsContributionsModule;
use App\Models\City;
use App\Models\Domain;
use App\Services\ContributionFeed;
use App\Services\ContributionStats;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Page Contributions (C1, D-207) : Agir → Contributions. Les besoins publiés et les contributions proposées publiquement, du plus récent
 * au plus ancien (jamais de classement), avec des filtres par forme d'aide, bénéficiaire, domaine et localité. Aucun paiement n'existe ici :
 * les compteurs sont des faits enregistrés, jamais des montants.
 */
#[Layout('components.layouts.app')]
#[Title('Contributions')]
class CarrefourContributions extends Component
{
    use GuardsContributionsModule, WithPagination;

    public const PER_PAGE = 12;

    public const PREVIEW = 6;

    /** Onglet : le contenu (tous, besoins, contributions) ou le bénéficiaire (personnes, ZUMRA, projets, entreprises). */
    public const TABS = [
        'tous' => 'Tous',
        'besoins' => 'Besoins',
        'contributions' => 'Contributions',
        'personnes' => 'Personnes',
        'zumra' => 'ZUMRA',
        'projets' => 'Projets',
        'entreprises' => 'Entreprises',
    ];

    private const TAB_BENEFICIARY = [
        'personnes' => BeneficiaryKind::Person,
        'zumra' => BeneficiaryKind::Zumra,
        'projets' => BeneficiaryKind::Project,
        'entreprises' => BeneficiaryKind::Company,
    ];

    #[Url(as: 'onglet', except: 'tous')]
    public string $tab = 'tous';

    #[Url(as: 'q', except: '')]
    public string $query = '';

    /** @var array<int, string> */
    #[Url(as: 'type', except: [])]
    public array $kinds = [];

    /** @var array<int, string> */
    #[Url(as: 'pour', except: [])]
    public array $beneficiaries = [];

    #[Url(as: 'domaine', except: '')]
    public string $domain = '';

    #[Url(as: 'ville', except: '')]
    public string $city = '';

    public function updated(string $property): void
    {
        $this->resetPage('besoins');
        $this->resetPage('propositions');
    }

    public function showTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'tous';
        $this->resetPage('besoins');
        $this->resetPage('propositions');
    }

    public function clearFilters(): void
    {
        $this->reset('query', 'kinds', 'beneficiaries', 'domain', 'city');
        $this->resetPage('besoins');
        $this->resetPage('propositions');
    }

    public function render(ContributionFeed $feed, ContributionStats $stats)
    {
        $viewer = Auth::user();
        $tab = array_key_exists($this->tab, self::TABS) ? $this->tab : 'tous';

        $beneficiaries = array_values(array_intersect($this->beneficiaries, array_column(BeneficiaryKind::cases(), 'value')));
        $tabBeneficiary = self::TAB_BENEFICIARY[$tab] ?? null;
        $contradiction = false;

        if ($tabBeneficiary) {
            // Un onglet de bénéficiaire contredit par un filtre ne montre rien, plutôt que tout.
            $contradiction = $beneficiaries !== [] && ! in_array($tabBeneficiary->value, $beneficiaries, true);
            $beneficiaries = [$tabBeneficiary->value];
        }

        $filters = [
            'q' => Str::limit(trim($this->query), 100, ''),
            'kinds' => array_values(array_intersect($this->kinds, array_column(HelpKind::cases(), 'value'))),
            'beneficiaries' => $beneficiaries,
            'domain' => Str::isUuid($this->domain) ? $this->domain : '',
            'city' => Str::isUuid($this->city) ? $this->city : '',
        ];

        $needsQuery = $feed->needs($viewer, $filters)->withCount(['proposals as proposals_count' => fn (Builder $q) => $q->whereIn('state', [ProposalState::Proposed->value, ProposalState::Accepted->value, ProposalState::Declared->value, ProposalState::Realized->value])]);
        $proposalsQuery = $feed->proposals($viewer, $filters);

        $showNeeds = $tab !== 'contributions';
        $showProposals = $tab !== 'besoins';
        $needsQuery = $contradiction ? $needsQuery->whereRaw('1 = 0') : $needsQuery;
        $proposalsQuery = $contradiction ? $proposalsQuery->whereRaw('1 = 0') : $proposalsQuery;
        $paginate = in_array($tab, ['besoins', 'contributions'], true);

        $needs = $showNeeds ? ($paginate ? $needsQuery->paginate(self::PER_PAGE, ['*'], 'besoins') : $needsQuery->limit($tab === 'tous' ? self::PREVIEW : self::PER_PAGE)->get()) : collect();
        $proposals = $showProposals ? ($paginate ? $proposalsQuery->paginate(self::PER_PAGE, ['*'], 'propositions') : $proposalsQuery->limit($tab === 'tous' ? self::PREVIEW : self::PER_PAGE)->get()) : collect();

        $hasFilters = $filters['q'] !== '' || $filters['kinds'] !== [] || $this->beneficiaries !== [] || $filters['domain'] !== '' || $filters['city'] !== '';

        return view('livewire.carrefour-contributions', [
            'viewer' => $viewer,
            'tab' => $tab,
            'tabs' => self::TABS,
            'needs' => $needs,
            'proposals' => $proposals,
            'paginate' => $paginate,
            'showNeeds' => $showNeeds,
            'showProposals' => $showProposals,
            'hasFilters' => $hasFilters,
            'helpKinds' => HelpKind::cases(),
            'beneficiaryKinds' => BeneficiaryKind::cases(),
            'domains' => Domain::query()->where(fn (Builder $q) => $q->where('active', true)->when($filters['domain'] !== '', fn (Builder $c) => $c->orWhere('id', $filters['domain'])))->ordered()->get(),
            'cities' => City::query()->where(fn (Builder $q) => $q->where('active', true)->when($filters['city'] !== '', fn (Builder $c) => $c->orWhere('id', $filters['city'])))->get()->sortBy(fn (City $c) => Normalizer::key($c->label()))->values(),
            'mine' => $stats->personal($viewer),
            'community' => $stats->community(),
        ]);
    }
}
