<?php

use App\Admin\Settings\Actions\CheckLicenseAction;
use App\Admin\Settings\BrandSettings;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\License\LicenseKeys;

/**
 * CheckLicenseAction, the daily license check: a key chatvia.ai still has
 * active for this domain stays, an outage at chatvia.ai changes nothing, and
 * without a key nothing is asked. Removing a deactivated key is in
 * tests/Feature/Admin/CheckLicenseCommandTest.php.
 */
beforeEach(function (): void {
    LicenseKeys::trust();
    config([
        'app.url' => 'https://chat.acme.com',
        'chatvia.license.api_url' => 'https://licenses.test/api/licenses',
    ]);

    saveSettings(BrandSettings::class, [
        'powered_by_enabled' => false,
        'license_key' => LicenseKeys::activatedFor('chat.acme.com'),
    ]);
});

it('keeps a key that is still active', function (): void {
    Http::fake(['licenses.test/api/licenses/check' => Http::response(['active' => true])]);

    expect(app(CheckLicenseAction::class)->execute())->toBeFalse()
        ->and(savedSetting(BrandSettings::class, 'license_key'))->toBe(LicenseKeys::activatedFor('chat.acme.com'))
        ->and(config('chatvia.powered_by'))->toBeFalse();
});

it('keeps the key when chatvia.ai gives no answer', function (Closure $answer): void {
    Http::fake($answer);

    expect(app(CheckLicenseAction::class)->execute())->toBeFalse()
        ->and(savedSetting(BrandSettings::class, 'license_key'))->toBe(LicenseKeys::activatedFor('chat.acme.com'));
})->with([
    'unreachable' => fn (): Closure => fn (): never => throw new ConnectionException('Connection refused'),
    'an error' => fn (): Closure => fn (): PromiseInterface => Http::response('Bad gateway', 502),
]);

it('asks nothing without a key that works here', function (): void {
    Http::fake();

    saveSettings(BrandSettings::class, ['license_key' => null]);

    expect(app(CheckLicenseAction::class)->execute())->toBeFalse();

    Http::assertNothingSent();
});
