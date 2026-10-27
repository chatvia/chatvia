<?php

use App\Admin\Settings\Actions\CheckLicenseAction;
use App\Admin\Settings\BrandSettings;
use App\Admin\Settings\CreditLicense;
use App\Admin\Settings\LicenseStatus;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\License\LicenseKeys;

/**
 * CheckLicenseAction, the daily license check: a key chatvia.ai still has
 * active for this domain stays (swapped for the fresh key it sends, so a
 * renewal moves the last day on), an outage at chatvia.ai changes nothing
 * (a yearly key still stops on its last day), and without a key nothing is
 * asked. Removing a deactivated key is in
 * tests/Feature/App/Console/Commands/License/CheckLicenseTest.php.
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

it('saves the fresh key of a renewed license', function (): void {
    $this->travelTo('2027-11-10 06:00:00');
    saveSettings(BrandSettings::class, ['license_key' => LicenseKeys::enterpriseUntil('2027-11-15', 'chat.acme.com')]);
    $renewed = LicenseKeys::enterpriseUntil('2028-11-15', 'chat.acme.com');

    Http::fake(['licenses.test/api/licenses/check' => Http::response(['active' => true, 'paid_until' => '2028-11-01', 'key' => $renewed])]);

    expect(app(CheckLicenseAction::class)->execute())->toBeFalse()
        ->and(savedSetting(BrandSettings::class, 'license_key'))->toBe($renewed)
        ->and(config('chatvia.license.enterprise'))->toBeTrue();
});

it('brings back an expired key that was renewed since', function (): void {
    $this->travelTo('2027-11-20 06:00:00');
    saveSettings(BrandSettings::class, ['license_key' => LicenseKeys::enterpriseUntil('2027-11-15', 'chat.acme.com')]);
    $renewed = LicenseKeys::enterpriseUntil('2028-11-15', 'chat.acme.com');

    expect(config('chatvia.license.licensed'))->toBeFalse();

    Http::fake(['licenses.test/api/licenses/check' => Http::response(['active' => true, 'key' => $renewed])]);

    app(CheckLicenseAction::class)->execute();

    expect(savedSetting(BrandSettings::class, 'license_key'))->toBe($renewed)
        ->and(config('chatvia.license.licensed'))->toBeTrue();
});

it('keeps an expired key locked when chatvia.ai gives no answer', function (): void {
    $this->travelTo('2027-11-20 06:00:00');
    $expired = LicenseKeys::enterpriseUntil('2027-11-15', 'chat.acme.com');
    saveSettings(BrandSettings::class, ['license_key' => $expired]);

    Http::fake(fn (): never => throw new ConnectionException('Connection refused'));

    expect(app(CheckLicenseAction::class)->execute())->toBeFalse()
        ->and(savedSetting(BrandSettings::class, 'license_key'))->toBe($expired)
        ->and(app(CreditLicense::class)->status($expired))->toBe(LicenseStatus::Expired)
        ->and(config('chatvia.license.licensed'))->toBeFalse();
});

it('ignores a fresh key that is not for this license or this domain', function (string $fresh): void {
    Http::fake(['licenses.test/api/licenses/check' => Http::response(['active' => true, 'key' => $fresh])]);

    app(CheckLicenseAction::class)->execute();

    expect(savedSetting(BrandSettings::class, 'license_key'))->toBe(LicenseKeys::activatedFor('chat.acme.com'));
})->with([
    'another domain' => fn (): string => LicenseKeys::activatedFor('chat.other.com'),
    'already ended' => fn (): string => LicenseKeys::enterpriseUntil('2020-01-01', 'chat.acme.com'),
    'not a key' => 'hello',
]);
