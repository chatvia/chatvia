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

it('asks nothing without a key, and changes nothing when chatvia.ai gives no answer, so an expired key stays locked', function (Closure $answer): void {
    $this->travelTo('2027-11-20 06:00:00');
    Http::fake($answer);
    saveSettings(BrandSettings::class, ['license_key' => null]);

    expect(app(CheckLicenseAction::class)->execute())->toBeFalse();
    Http::assertNothingSent();

    $expired = LicenseKeys::enterpriseUntil('2027-11-15', 'chat.acme.com');
    saveSettings(BrandSettings::class, ['license_key' => $expired]);

    expect(app(CheckLicenseAction::class)->execute())->toBeFalse()
        ->and(savedSetting(BrandSettings::class, 'license_key'))->toBe($expired)
        ->and(app(CreditLicense::class)->status($expired))->toBe(LicenseStatus::Expired)
        ->and(config('chatvia.license.licensed'))->toBeFalse();
})->with([
    'unreachable' => fn (): Closure => fn (): never => throw new ConnectionException('Connection refused'),
    'an error' => fn (): Closure => fn (): PromiseInterface => Http::response('Bad gateway', 502),
]);

it('keeps a key that is still active, and ignores a fresh key that is not for this license or this domain', function (array $answer): void {
    Http::fake(['licenses.test/api/licenses/check' => Http::response($answer)]);

    expect(app(CheckLicenseAction::class)->execute())->toBeFalse()
        ->and(savedSetting(BrandSettings::class, 'license_key'))->toBe(LicenseKeys::activatedFor('chat.acme.com'))
        ->and(config('chatvia.powered_by'))->toBeFalse();
})->with([
    'no fresh key' => [['active' => true]],
    'another domain' => fn (): array => ['active' => true, 'key' => LicenseKeys::activatedFor('chat.other.com')],
    'already ended' => fn (): array => ['active' => true, 'key' => LicenseKeys::enterpriseUntil('2020-01-01', 'chat.acme.com')],
    'not a key' => [['active' => true, 'key' => 'hello']],
]);

it('saves the fresh key of a renewed license, and brings back an expired one', function (string $now): void {
    $this->travelTo($now);
    saveSettings(BrandSettings::class, ['license_key' => LicenseKeys::enterpriseUntil('2027-11-15', 'chat.acme.com')]);
    $renewed = LicenseKeys::enterpriseUntil('2028-11-15', 'chat.acme.com');
    Http::fake(['licenses.test/api/licenses/check' => Http::response(['active' => true, 'paid_until' => '2028-11-01', 'key' => $renewed])]);

    expect(app(CheckLicenseAction::class)->execute())->toBeFalse()
        ->and(savedSetting(BrandSettings::class, 'license_key'))->toBe($renewed)
        ->and(config('chatvia.license.licensed'))->toBeTrue()
        ->and(config('chatvia.license.enterprise'))->toBeTrue();
})->with([
    'before the last day' => '2027-11-10 06:00:00',
    'after the last day' => '2027-11-20 06:00:00',
]);
