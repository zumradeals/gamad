<?php

use App\Core\Execution\Execution;
use App\Livewire\TraitementsCore;
use App\Models\User;
use App\Services\Moderation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

// Première observation du Core : réservée au super administrateur, alimentée par des données réelles, sans contenu ni personne.

it('est réservée au super administrateur', function () {
    $this->get(route('espace.traitements'))->assertRedirect();
    $this->actingAs(User::factory()->create())->get(route('espace.traitements'))->assertForbidden();
    $this->actingAs(User::factory()->moderator()->create())->get(route('espace.traitements'))->assertForbidden();
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('espace.traitements'))->assertOk()->assertSee('Traitements du Core');
});

it('affiche les mesures réelles : file, exécutions, recherches, pistes et motifs de retrait', function () {
    $author = person('Atelier de couture', ['Couture']);
    $helper = person('Personne disponible', ['Couture']);
    matchingPreferences($author);
    matchingPreferences($helper);
    $post = matchingPublication($author);
    Execution::dispatch(new RunMatchingSearchProbe);
    app(Moderation::class)->hide(User::factory()->superAdmin()->create(), $post, 'Motif de recette');

    $page = Livewire::actingAs(User::factory()->superAdmin()->create())->test(TraitementsCore::class);

    $page->assertSeeText('RunMatchingSearch')->assertSeeText('Publication masquée : 2')->assertSeeText('2 retirée(s)')
        ->assertSeeText('Aucun échec définitif');
    expect($page->html())->toContain('data-testid="queue-due"');

    $page->call('setHours', 168)->assertSeeText('Exécutions sur la période');
});

class RunMatchingSearchProbe implements ShouldQueue
{
    use Queueable;

    public function handle(): void {}
}

it('ne montre ni contenu, ni nom, ni adresse : seulement des comptes, des durées et des classes d’erreur', function () {
    $author = person('Zoulou Secret', ['Couture']);
    $helper = person('Yvonne Confidentielle', ['Couture']);
    matchingPreferences($author);
    matchingPreferences($helper);
    matchingPublication($author, data: ['body' => 'Texte confidentiel XYZ pour la couture']);
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\Matching\\RunMatchingSearch']), 'exception' => "RuntimeException: échec pour jean@secret.test à Abidjan\n#0 trace",
    ]);

    $html = Livewire::actingAs(User::factory()->superAdmin()->create())->test(TraitementsCore::class)->html();

    foreach (['Zoulou', 'Yvonne', 'Texte confidentiel', 'jean@secret.test', $author->email, $helper->email, $author->id, $helper->id] as $secret) {
        expect($html)->not->toContain($secret);
    }
    expect($html)->toContain('RuntimeException')->toContain('RunMatchingSearch');
});

it('affiche « aucune » plutôt que des chiffres inventés quand rien n’a été exécuté', function () {
    Livewire::actingAs(User::factory()->superAdmin()->create())->test(TraitementsCore::class)
        ->assertSeeText('Aucune exécution sur cette période')->assertSeeText('Aucun retrait enregistré')->assertSeeText('Aucun échec définitif');
});

it('n’est pas accessible par appel direct de composant pour une personne ordinaire', function () {
    Livewire::actingAs(User::factory()->create())->test(TraitementsCore::class)->assertNotFound();
});

it('est proposée depuis Mon espace › Administration au super administrateur seulement', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('espace', ['rubrique' => 'administration']))->assertSee('Traitements du Core');
    $this->actingAs(User::factory()->moderator()->create())->get(route('espace', ['rubrique' => 'administration']))->assertDontSee('Traitements du Core');
});
