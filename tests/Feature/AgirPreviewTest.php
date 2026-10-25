<?php

use App\Livewire\PublierPublication;
use App\Models\Publication;
use Livewire\Livewire;

it('présente cinq actions avec les destinations existantes', function () {
    $this->actingAs(person('Awa'))->get(route('agir'))->assertOk()
        ->assertSeeText('J’ai besoin d’aide')->assertSeeText('Je veux apprendre')
        ->assertSeeText('Je peux aider')->assertSeeText('Je partage un savoir')->assertSeeText('Je montre une réalisation');
});

it('relit sans publier et conserve les précisions après modification', function () {
    $user = person('Awa');
    $count = Publication::count();
    Livewire::actingAs($user)->test(PublierPublication::class, ['slug' => 'besoin'])
        ->set('body', 'Installer une caisse')->set('needPurpose', 'Ma boutique')
        ->call('preview')->assertHasNoErrors()->assertSet('previewing', true)
        ->assertSeeText('Pour : Ma boutique')->call('edit')->assertSet('previewing', false)
        ->assertSet('body', 'Installer une caisse')->assertSet('needPurpose', 'Ma boutique');
    expect(Publication::count())->toBe($count);
});

it('refuse un aperçu vide ou dépassant la limite composée', function () {
    Livewire::actingAs(person('Awa'))->test(PublierPublication::class, ['slug' => 'besoin'])
        ->call('preview')->assertHasErrors('body')->assertSet('previewing', false)
        ->set('body', str_repeat('a', 490))->set('needPurpose', 'Une boutique entière')
        ->call('preview')->assertHasErrors('body')->assertSet('previewing', false);
});
