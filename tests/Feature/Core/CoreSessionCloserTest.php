<?php

use App\Services\Core\CoreSessionCloser;
use Illuminate\Support\Facades\Http;

it('révoque une session auprès du Core et exige une confirmation explicite', function () {
    config()->set('services.gamad_core.url', 'https://core.example.test');
    Http::fake(['core.example.test/api/v1/sessions/current' => Http::response([
        'revoquee' => true,
        'jetons_federes_fermes' => 1,
        'preuve' => 'audit',
    ])]);

    expect(app(CoreSessionCloser::class)->close('secret-token'))->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request->url() === 'https://core.example.test/api/v1/sessions/current');
});

it('refuse une révocation non confirmée ou une configuration non sécurisée', function () {
    config()->set('services.gamad_core.url', 'http://core.example.test');
    expect(app(CoreSessionCloser::class)->close('secret-token'))->toBeFalse();
    Http::assertNothingSent();

    config()->set('services.gamad_core.url', 'https://core.example.test');
    Http::fake(['core.example.test/*' => Http::response(['revoquee' => false])]);
    expect(app(CoreSessionCloser::class)->close('secret-token'))->toBeFalse();

    Http::fake(['core.example.test/*' => Http::response(['erreur' => 'INDISPONIBLE'], 503)]);
    expect(app(CoreSessionCloser::class)->close('secret-token'))->toBeFalse();
});
