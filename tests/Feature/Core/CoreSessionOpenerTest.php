<?php

use App\Services\Core\CoreSessionOpener;
use Illuminate\Support\Facades\Http;

it('ouvre une session exclusivement par le contrat API du Core', function () {
    config()->set('services.gamad_core.url', 'https://core.example.test');
    Http::fake(['core.example.test/api/v1/sessions' => Http::response([
        'type' => 'Bearer',
        'jeton' => 'opaque-token',
        'entite' => 'AUT-TEST-001',
        'expire_le' => '2026-11-06T10:00:00+00:00',
        'preuve' => 'private',
    ], 201)]);

    expect(app(CoreSessionOpener::class)->open('membre@example.test', 'secret-test'))->toBe([
        'jeton' => 'opaque-token',
        'entite' => 'AUT-TEST-001',
        'expire_le' => '2026-11-06T10:00:00+00:00',
    ]);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['identifiant'] === 'membre@example.test'
        && $request['type_identifiant'] === 'EMAIL'
        && $request['secret'] === 'secret-test');
});

it('échoue fermé sans Core HTTPS ou si la session est refusée', function () {
    config()->set('services.gamad_core.url', null);
    expect(app(CoreSessionOpener::class)->open('membre@example.test', 'secret'))->toBeNull();
    Http::assertNothingSent();

    config()->set('services.gamad_core.url', 'https://core.example.test');
    Http::fake(['core.example.test/*' => Http::response(['erreur' => 'AUTHENTIFICATION_REFUSEE'], 401)]);
    expect(app(CoreSessionOpener::class)->open('membre@example.test', 'secret'))->toBeNull();

    Http::fake(['core.example.test/*' => Http::response(['type' => 'Bearer', 'jeton' => 'x'], 201)]);
    expect(app(CoreSessionOpener::class)->open('membre@example.test', 'secret'))->toBeNull();
});
