<?php

use App\Services\Core\CoreRegistrationClient;
use Illuminate\Support\Facades\Http;

it('accepte seulement la réponse contractuelle du Core', function () {
    config()->set('services.gamad_core.url', 'https://core.example.test');
    Http::fake(['core.example.test/api/v1/inscriptions/publiques' => Http::response([
        'identite' => 'IDN-GAMAD-001',
        'verification_requise' => true,
    ], 201)]);

    expect(app(CoreRegistrationClient::class)->register('Membre', 'membre@example.test', 'secret-test'))
        ->toBe(['identite' => 'IDN-GAMAD-001', 'verification_requise' => true]);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['nom'] === 'Membre'
        && $request['email'] === 'membre@example.test'
        && $request['secret'] === 'secret-test');
});

it('échoue fermé sans Core sécurisé ou si le contrat est indisponible', function () {
    config()->set('services.gamad_core.url', null);
    expect(app(CoreRegistrationClient::class)->register('Membre', 'membre@example.test', 'secret'))->toBeNull();
    Http::assertNothingSent();

    config()->set('services.gamad_core.url', 'https://core.example.test');
    Http::fake(['core.example.test/*' => Http::response(['message' => 'Not Found'], 404)]);
    expect(app(CoreRegistrationClient::class)->register('Membre', 'membre@example.test', 'secret'))->toBeNull();

    Http::fake(['core.example.test/*' => Http::response(['identite' => 'IDN-GAMAD-001'], 201)]);
    expect(app(CoreRegistrationClient::class)->register('Membre', 'membre@example.test', 'secret'))->toBeNull();
});
