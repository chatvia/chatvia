<?php

use App\Admin\Settings\Actions\ActivateLicenseAction;
use App\Admin\Settings\BrandSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\License\LicenseKeys;

/**
 * ActivateLicenseAction: a key as bought is activated for this domain at
 * chatvia.ai and the activated key is saved; a key that is not real, or that
 * chatvia.ai refuses, saves nothing. The command line is in
 * tests/Feature/App/Console/Commands/License/ActivateLicenseTest.php.
 */
beforeEach(function (): void {
    LicenseKeys::trust();
    config([
        'app.url' => 'https://chat.acme.com',
        'chatvia.license.api_url' => 'https://licenses.test/api/licenses',
    ]);
});

it('saves the key chatvia.ai activated for this domain', function (): void {
    Http::fake(['licenses.test/api/licenses/activate' => Http::response(['key' => LicenseKeys::activatedFor('chat.acme.com')])]);

    $activation = app(ActivateLicenseAction::class)->execute(LicenseKeys::BOUGHT_KEY);

    expect($activation->key)->toBe(LicenseKeys::activatedFor('chat.acme.com'))
        ->and(savedSetting(BrandSettings::class, 'license_key'))->toBe(LicenseKeys::activatedFor('chat.acme.com'));

    Http::assertSent(fn (Request $request): bool => $request['key'] === LicenseKeys::BOUGHT_KEY
        && $request['domain'] === 'chat.acme.com');
});

it('sends nothing for a key that is not real', function (): void {
    Http::fake();

    $activation = app(ActivateLicenseAction::class)->execute('cvl1.not.real');

    expect($activation->key)->toBeNull()
        ->and($activation->message)->toContain('not a valid license key')
        ->and(savedSetting(BrandSettings::class, 'license_key'))->toBeNull();

    Http::assertNothingSent();
});

it('keeps the saved key when the activation fails', function (Closure $answer, string $message): void {
    saveSettings(BrandSettings::class, ['license_key' => LicenseKeys::activatedFor('chat.acme.com')]);
    Http::fake($answer);

    $activation = app(ActivateLicenseAction::class)->execute(LicenseKeys::BOUGHT_KEY);

    expect($activation->key)->toBeNull()
        ->and($activation->message)->toContain($message)
        ->and(savedSetting(BrandSettings::class, 'license_key'))->toBe(LicenseKeys::activatedFor('chat.acme.com'));
})->with([
    'refused' => [fn (): Closure => fn () => Http::response(['message' => 'This key is active on another domain.'], 422), 'another domain'],
    'unreachable' => [fn (): Closure => fn (): never => throw new ConnectionException('Connection refused'), 'could not be reached'],
]);
