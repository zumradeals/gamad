<?php

namespace App\Livewire;

use App\Enums\BeneficiaryKind;
use App\Enums\HelpKind;
use App\Livewire\Concerns\GuardsContributionsModule;
use App\Models\Company;
use App\Models\ContributionProposal;
use App\Models\Domain;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Publication;
use App\Models\User;
use App\Models\Zumra;
use App\Services\ContributionAccess;
use App\Services\ContributionProposals;
use App\Support\ZumraModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * « Proposer une contribution » (C1, D-207), en quatre questions : comment contribuer, à qui, que proposez-vous concrètement, un message
 * facultatif. En réponse à un besoin (?besoin=), le bénéficiaire est celui du besoin et la forme d'aide est préremplie ; depuis une
 * page (?zumra=, ?projet=, ?entreprise=, ?personne=), le bénéficiaire est celui de la page. Rien n'est une réalisation avant la
 * confirmation du bénéficiaire ; une intention financière n'est ni payée ni encaissée.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Proposer une contribution')]
class ProposerContribution extends Component
{
    use GuardsContributionsModule;

    /** Le besoin auquel on répond ; verrouillé, il ne change pas côté navigateur. */
    #[Locked]
    public ?string $needId = null;

    public string $kind = '';

    /** « gamad », ou « zumra:identifiant », « project:… », « company:… », « person:… ». */
    public string $target = '';

    public string $offer = '';

    public string $message = '';

    public string $visibility = ContributionProposal::PRIVATE;

    public string $domain_id = '';

    public string $city_id = '';

    public function mount(): void
    {
        $user = Auth::user();
        $need = request()->query('besoin');

        if (is_string($need) && Str::isUuid($need)) {
            $publication = Publication::query()->find($need);

            // Le besoin doit exister pour cette personne : sinon 404, comme pour toute publication invisible.
            abort_unless($publication && Gate::forUser($user)->allows('view', $publication), 404);

            $this->needId = $publication->id;
            $this->kind = (string) $publication->help_kind;

            return;
        }

        if (request()->query('zumra') && Str::isUuid((string) request()->query('zumra'))) {
            $this->target = 'zumra:'.request()->query('zumra');
        } elseif (request()->query('projet') && Str::isUuid((string) request()->query('projet'))) {
            $this->target = 'project:'.request()->query('projet');
        } elseif (request()->query('entreprise') && Str::isUuid((string) request()->query('entreprise'))) {
            $this->target = 'company:'.request()->query('entreprise');
        } elseif (is_string(request()->query('personne')) && ($profile = Profile::query()->where('public_id', request()->query('personne'))->first())) {
            $this->target = 'person:'.$profile->user_id;
        } elseif (request()->query('gamad')) {
            $this->target = 'gamad';
        }
    }

    public function save(ContributionProposals $proposals)
    {
        $this->resetErrorBag();

        [$type, $id] = array_pad(explode(':', $this->target, 2), 2, null);

        $proposal = $proposals->propose(Auth::user(), [
            'kind' => $this->kind,
            'offer' => $this->offer,
            'message' => $this->message,
            'visibility' => $this->visibility,
            'domain_id' => $this->domain_id,
            'city_id' => $this->city_id,
            'need' => $this->needId ? Publication::query()->find($this->needId) : null,
            'beneficiary' => [$type, $id],
        ]);

        session()->flash('status', 'Votre proposition est transmise. Elle ne devient une contribution qu’une fois acceptée, puis confirmée comme réalisée.');

        return $this->redirect(route('contributions.show', $proposal), navigate: false);
    }

    public function render()
    {
        $user = Auth::user();
        $access = app(ContributionAccess::class);
        $need = $this->needId ? Publication::query()->with(['author.profile', 'zumra', 'project', 'company'])->find($this->needId) : null;

        $beneficiaryLabel = null;
        $beneficiaryKind = null;

        if ($need) {
            [$beneficiaryKind, $model] = $access->beneficiary($need);
            $beneficiaryLabel = match ($beneficiaryKind) {
                BeneficiaryKind::Gamad => 'GAMAD',
                BeneficiaryKind::Person => $need->author->name,
                default => $model?->name,
            };
        } elseif (Str::startsWith($this->target, 'person:') && ($person = User::query()->find(Str::after($this->target, 'person:')))) {
            $beneficiaryKind = BeneficiaryKind::Person;
            $beneficiaryLabel = $person->name;
        }

        $zumraOpen = ZumraModule::accessibleTo($user);
        [$targetType] = array_pad(explode(':', $this->target, 2), 2, null);

        return view('livewire.proposer-contribution', [
            'need' => $need,
            'fixedBeneficiary' => $need !== null || Str::startsWith($this->target, 'person:'),
            'beneficiaryKind' => $beneficiaryKind,
            'beneficiaryLabel' => $beneficiaryLabel,
            'kinds' => HelpKind::cases(),
            'targetType' => $targetType,
            'zumras' => $zumraOpen ? Zumra::query()->orderBy('name_key')->limit(300)->get(['id', 'name']) : collect(),
            'projects' => $zumraOpen ? Project::query()->showcased()->orderBy('name_key')->limit(300)->get(['id', 'name']) : collect(),
            'companies' => Company::query()->publiclyVisible()->orderBy('name')->limit(300)->get(['id', 'name']),
            'domains' => Domain::query()->where('active', true)->ordered()->get(),
            // Une proposition publique ne révèle pas une personne : privée seulement pour une personne hors besoin public.
            'canBePublic' => $need ? $need->visibility === Publication::PUBLIC : $targetType !== 'person',
        ]);
    }
}
