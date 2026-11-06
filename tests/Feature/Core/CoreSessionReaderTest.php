<?php

use App\Services\Core\CoreSessionReader;
use Illuminate\Support\Facades\Http;

it('lit uniquement une session attestée par GAMAD Core', function () {
    config()->set('services.gamad_core.url', 'https://core.example.test');
    Http::fake(['core.example.test/api/v1/sessions/current' => Http::response([
        'entite' => 'AUT-GAMAD-001',
        'assurance' => 'STANDARD',
        'expire_le' => '2026-11-06T10:00:00+00:00',
        'jeton' => 'ne-jamais-exposer',
    ])]);

    expect(app(CoreSessionReader::class)->current('jeton-de-test'))->toBe([
        'entite' => 'AUT-GAMAD-001',
        'assurance' => 'STANDARD',
        'expire_le' => '2026-11-06T10:00:00+00:00',
    ]);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer jeton-de-test'));
});

it('refuse toute session lorsque le Core est absent, invalide ou indisponible', function () {
    config()->set('services.gamad_core.url', null);
    expect(app(CoreSessionReader::class)->current('jeton'))->toBeNull();
    Http::assertNothingSent();

    config()->set('services.gamad_core.url', 'http://core.example.test');
    expect(app(CoreSessionReader::class)->current('jeton'))->toBeNull();
    Http::assertNothingSent();

    config()->set('services.gamad_core.url', 'https://core.example.test');
    Http::fake(['core.example.test/*' => Http::response(['erreur' => 'REFUSE'], 401)]);
    expect(app(CoreSessionReader::class)->current('jeton'))->toBeNull();

    Http::fake(['core.example.test/*' => Http::response(['entite' => 'AUT-GAMAD-001'], 200)]);
    expect(app(CoreSessionReader::class)->current('jeton'))->toBeNull();
});
