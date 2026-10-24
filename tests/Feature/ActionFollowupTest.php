<?php

use App\Enums\PublicationType;
use App\Livewire\PagePublication;
use App\Livewire\PublierPublication;
use App\Models\Publication;
use App\Models\User;
use App\Services\Moderation;
use App\Services\Publications;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('enregistre modifie et efface le bilan personnel résolu sans publier de réalisation', function () {
    $author = person('Awa');
    $post = publication($author);
    $service = app(Publications::class);
    $service->resolve($author, $post);
    $count = Publication::count();
    Livewire::actingAs($author)->test(PagePublication::class, ['publication' => $post])
        ->set('outcome', 'La caisse fonctionne.')->call('saveOutcome')->assertHasNoErrors()
        ->assertSeeText('La caisse fonctionne.')->assertSeeText('Montrer une réalisation');
    expect($post->fresh()->outcome)->toBe('La caisse fonctionne.');
    $service->saveOutcome($author, $post, 'Installation terminée.');
    expect($post->fresh()->outcome)->toBe('Installation terminée.');
    $service->saveOutcome($author, $post, '  ');
    expect($post->fresh()->outcome)->toBeNull()->and(Publication::count())->toBe($count);
});

it('refuse les bilans étrangers ouverts non besoins trop longs et masqués', function () {
    $author = person('Awa');
    $other = person('Yao');
    $post = publication($author);
    $service = app(Publications::class);
    expect(fn () => $service->saveOutcome($author, $post, 'Fin'))->toThrow(HttpException::class);
    $service->resolve($author, $post);
    expect(fn () => $service->saveOutcome($other, $post, 'Fin'))->toThrow(AuthorizationException::class);
    expect(fn () => $service->saveOutcome($author, $post, str_repeat('a', 281)))->toThrow(ValidationException::class);
    $share = publication($author, PublicationType::Share);
    expect(fn () => $service->saveOutcome($author, $share, 'Fin'))->toThrow(HttpException::class);
    app(Moderation::class)->hide(User::factory()->superAdmin()->create(), $post, 'Contrôle du bilan masqué');
    expect(fn () => $service->saveOutcome($author, $post, 'Fin'))->toThrow(HttpException::class);
});

it('conserve l’audience du bilan et échappe son texte', function () {
    $author = person('Awa');
    $post = publication($author, data: ['visibility' => Publication::MEMBERS]);
    $service = app(Publications::class);
    $service->resolve($author, $post);
    $service->saveOutcome($author, $post, '<script>secret-bilan</script>');
    $this->get(route('publications.show', $post))->assertNotFound();
    $this->actingAs(person('Yao'))->get(route('publications.show', $post))->assertOk()
        ->assertSee('&lt;script&gt;secret-bilan&lt;/script&gt;', false)->assertDontSee('<script>secret-bilan</script>', false);
});

it('n’efface le brouillon et ne propose la suite qu’après publication réussie', function () {
    $author = person('Awa');
    Livewire::actingAs($author)->test(PublierPublication::class, ['slug' => 'partage'])
        ->call('publish')->assertHasErrors('body');
    expect(session('clear_draft'))->toBeNull();
    Livewire::actingAs($author)->test(PublierPublication::class, ['slug' => 'partage'])
        ->set('body', 'Une expérience utile')->call('publish')->assertHasNoErrors();
    $post = $author->publications()->first();
    expect(session('clear_draft'))->toBe('gamad:draft:'.$author->id.':partage')
        ->and(session('published_id'))->toBe($post->id);
    $this->get(route('publications.show', $post))->assertSeeText('Votre publication est en ligne')
        ->assertSeeText('Lien à partager')->assertDontSeeText('Consulter mes pistes');
});

it('propose une seule prochaine amélioration du profil sans score', function () {
    $author = person('Awa');
    $this->actingAs($author)->get(route('espace'))->assertSeeText('Ajoutez une capacité à votre profil')
        ->assertDontSeeText('Montrez votre savoir-faire avec une réalisation concrète.');
    $skilled = person('Moussa', ['Plomberie']);
    $this->actingAs($skilled)->get(route('espace'))->assertSeeText('Montrez votre savoir-faire avec une réalisation concrète.');
    publication($skilled, PublicationType::PersonalWork);
    $this->get(route('espace'))->assertSeeText('Présentez votre expérience en quelques mots');
});

it('adapte l’accompagnement à l’intention sans changer les gestes', function () {
    expect(PublicationType::LearningWish->gestureLabel())->toBe('Je peux vous accompagner')
        ->and(PublicationType::Share->gestureLabel())->toBe('Échanger');
});
