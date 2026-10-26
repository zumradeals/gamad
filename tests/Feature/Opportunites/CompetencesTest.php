<?php

use App\Livewire\OpportunityCatalog;
use App\Livewire\OpportunityForm;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Skill;
use App\Models\User;
use App\Services\Opportunities;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

// Lot D1 : les compétences recherchées d'une opportunité sont des capacités du référentiel commun « skills ».

function entrepriseCompetences(?User $owner = null): Company
{
    $company = new Company(['name' => 'Atelier Soleil', 'legal_name' => 'Atelier Soleil SARL', 'registration_country' => 'CI',
        'registration_number' => 'RCCM-'.Str::random(10), 'sector' => 'Énergie', 'city' => 'Abidjan', 'summary' => 'Une entreprise de test.']);
    $company->slug = 'atelier-soleil-'.strtolower(Str::random(6));
    $company->forceFill(['owner_id' => ($owner ?? User::factory()->create())->id, 'status' => Company::VERIFIED, 'verified_at' => now()])->save();

    return $company;
}

function nomsCompetences(Opportunity $opportunity): array
{
    return $opportunity->fresh()->requiredSkills->pluck('name')->all();
}

beforeEach(function () {
    $this->company = entrepriseCompetences();
    $this->owner = $this->company->owner;
});

it('relie les compétences recherchées au référentiel commun, en réutilisant une capacité existante', function () {
    $existante = person('Awa Traoré', ['Comptabilité'])->profile->offeredSkills()->first();
    $form = Livewire::actingAs($this->owner)->test(OpportunityForm::class, ['company' => $this->company])
        ->call('chooseKind', 'job')->set('title', 'Comptable')->set('description', 'Tenir la comptabilité de l’entreprise.')
        ->call('addSkill', 'skills', 'COMPTABILITE')->call('addSkill', 'skills', 'Paie')->call('addSkill', 'skills', 'paie');
    expect($form->get('skills'))->toBe(['Comptabilité', 'Paie']);

    $form->call('publish')->assertHasNoErrors()->assertRedirect();
    $o = Opportunity::sole();

    expect(nomsCompetences($o))->toBe(['Comptabilité', 'Paie'])
        ->and($o->requiredSkills->first()->id)->toBe($existante->id)
        ->and(Skill::where('normalized', 'comptabilite')->count())->toBe(1)
        ->and(DB::table('opportunity_skill')->where('opportunity_id', $o->id)->count())->toBe(2);
});

it('modifie les compétences d’une opportunité existante et remet à jour la recherche', function () {
    $o = opportunite($this->company, ['title' => 'Technicien', 'skills' => ['Électricité', 'Réseaux']]);

    Livewire::actingAs($this->owner)->test(OpportunityForm::class, ['opportunity' => $o])
        ->assertSet('skills', ['Électricité', 'Réseaux'])
        ->call('removeSkill', 'skills', 0)->call('addSkill', 'skills', 'Soudure')
        ->call('publish')->assertHasNoErrors();

    expect(nomsCompetences($o))->toBe(['Réseaux', 'Soudure']);
    // La recherche suit : l'ancienne compétence n'est plus trouvée, la nouvelle l'est, sans accents ni casse.
    $trouve = fn (string $saisie) => Livewire::test(OpportunityCatalog::class)->set('query', $saisie)->viewData('opportunities')->pluck('id')->all();
    expect($trouve('electricite'))->toBe([])->and($trouve('SOUDURE'))->toBe([$o->id])->and($trouve('reseaux'))->toBe([$o->id]);
});

it('garde une opportunité sans compétence, et un brouillon, parfaitement valides', function () {
    Livewire::actingAs($this->owner)->test(OpportunityForm::class, ['company' => $this->company])
        ->call('chooseKind', 'mission')->set('title', 'Mission courte')->set('description', 'Une mission sans compétence précisée.')
        ->call('saveDraft')->assertHasNoErrors();
    $o = Opportunity::sole();

    expect(nomsCompetences($o))->toBe([])->and($o->status)->toBe('draft');
    $this->actingAs($this->owner)->get(route('opportunites.show', $o))->assertOk()->assertDontSee('aria-label="Compétences recherchées"', false);
});

it('affiche les compétences dans l’ordre choisi sur la page publique', function () {
    $o = opportunite($this->company, ['title' => 'Technicien', 'missions' => 'Installer', 'skills' => ['Réseaux', 'Électricité', 'Soudure']]);

    $html = $this->get(route('opportunites.show', $o))->assertOk()->assertSee('aria-label="Compétences recherchées"', false)->getContent();
    preg_match('/aria-label="Compétences recherchées"[^>]*>(.*?)<\/ul>/s', $html, $liste);

    expect(trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('</li>', ' </li>', $liste[1])))))->toBe('Réseaux Électricité Soudure');
});

it('refuse plus de huit compétences et une capacité invalide, avec un message clair', function () {
    $form = Livewire::actingAs($this->owner)->test(OpportunityForm::class, ['company' => $this->company]);
    foreach (['Une', 'Deux', 'Trois', 'Quatre', 'Cinq', 'Six', 'Sept', 'Huit'] as $nom) {
        $form->call('addSkill', 'skills', $nom);
    }
    $form->call('addSkill', 'skills', 'Neuf')->assertHasErrors('skillsInput')->assertCount('skills', 8);
    expect($form->get('skills'))->toHaveCount(8);

    $form->set('skillsInput', 'x')->call('addSkill', 'skills')->assertHasErrors('skillsInput');

    $service = app(Opportunities::class);
    expect(fn () => $service->clean(['kind' => 'job', 'title' => 'T', 'skills' => array_map(fn ($i) => 'Compétence '.$i, range(1, 9))], false))->toThrow(ValidationException::class)
        ->and(fn () => $service->clean(['kind' => 'job', 'title' => 'T', 'skills' => ['?']], false))->toThrow(ValidationException::class);
});

it('réserve la modification des compétences au propriétaire de l’entreprise', function () {
    $o = opportunite($this->company, ['title' => 'Technicien', 'skills' => ['Réseaux']]);
    $autre = User::factory()->create();

    $this->actingAs($autre)->get(route('opportunites.edit', $o))->assertForbidden();
    expect(fn () => app(Opportunities::class)->saveDraft($autre, $this->company, ['kind' => 'job', 'title' => 'Piraté', 'skills' => ['Intrus']], $o))->toThrow(AuthorizationException::class);
    expect(nomsCompetences($o))->toBe(['Réseaux'])->and(Skill::where('normalized', 'intrus')->exists())->toBeFalse();
    Livewire::actingAs($autre)->test(OpportunityForm::class, ['opportunity' => $o])->assertForbidden();
});

it('propose dans le sélecteur les capacités déjà utilisées par une opportunité', function () {
    opportunite($this->company, ['title' => 'Technicien', 'skills' => ['Hydraulique']]);
    Skill::findOrCreateByName('Capacité orpheline');

    $form = Livewire::actingAs($this->owner)->test(OpportunityForm::class, ['company' => $this->company])->set('skillsInput', 'hydr');
    expect($form->viewData('suggestions')->pluck('name')->all())->toBe(['Hydraulique']);
    expect(Livewire::actingAs($this->owner)->test(OpportunityForm::class, ['company' => $this->company])->set('skillsInput', 'orphe')->viewData('suggestions'))->toBeEmpty();
});

it('reprend sans rien supprimer les compétences saisies en texte libre avant D1', function () {
    $legacy = opportunite($this->company, ['title' => 'Ancienne offre']);
    DB::table('opportunities')->where('id', $legacy->id)->update(['skills' => json_encode(['Maçonnerie', 'maçonnerie', 'x', 'Peinture'])]);
    $deja = opportunite($this->company, ['title' => 'Déjà reliée', 'skills' => ['Plomberie']]);
    DB::table('opportunities')->where('id', $deja->id)->update(['skills' => json_encode(['Autre'])]);
    $sans = opportunite($this->company, ['title' => 'Sans compétence']);

    expect(Opportunity::linkLegacySkills())->toBe(1);

    expect(nomsCompetences($legacy))->toBe(['Maçonnerie', 'Peinture'])->and(nomsCompetences($deja))->toBe(['Plomberie'])->and(nomsCompetences($sans))->toBe([])
        ->and(json_decode(DB::table('opportunities')->where('id', $legacy->id)->value('skills'), true))->toBe(['Maçonnerie', 'maçonnerie', 'x', 'Peinture']);
    expect(Opportunity::linkLegacySkills())->toBe(0);
    expect(Livewire::test(OpportunityCatalog::class)->set('query', 'maconnerie')->viewData('opportunities')->pluck('id')->all())->toBe([$legacy->id]);
});

it('suggère aux personnes les opportunités dont une compétence recherchée est l’une de leurs capacités, sans score', function () {
    $moi = person('Moi Même', ['Électricité']);
    $lie = opportunite($this->company, ['title' => 'Technicien réseau', 'skills' => ['Électricité']]);
    opportunite($this->company, ['title' => 'Comptable', 'skills' => ['Paie']]);

    $suggestions = Livewire::actingAs($moi)->test(OpportunityCatalog::class)->viewData('forYou');

    expect($suggestions->pluck('id')->all())->toBe([$lie->id])->and($suggestions->first()->reason)->toBe('Correspond à votre capacité « Électricité »');
});

it('supprime les liens avec l’opportunité mais garde la capacité du référentiel', function () {
    $o = opportunite($this->company, ['title' => 'Technicien', 'skills' => ['Réseaux']]);
    $skill = Skill::where('normalized', 'reseaux')->firstOrFail();

    $o->delete();

    expect(DB::table('opportunity_skill')->count())->toBe(0)->and(Skill::find($skill->id))->not->toBeNull();
});

it('garde les précisions ouvertes pendant la saisie d’une compétence, pour que le sélecteur ne disparaisse pas', function () {
    $o = opportunite($this->company, ['title' => 'Technicien']);

    $form = Livewire::actingAs($this->owner)->test(OpportunityForm::class, ['opportunity' => $o]);
    expect($form->html())->not->toMatch('/<details[^>]*\sopen\s*>/');

    expect($form->set('skillsInput', 'soud')->html())->toMatch('/<details[^>]*\sopen\s*>/');
});
