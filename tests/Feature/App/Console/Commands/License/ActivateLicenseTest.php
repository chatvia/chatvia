<?php

use App\Admin\Settings\BrandSettings;
use Illuminate\Support\Facades\Http;
use Tests\Support\License\LicenseKeys;

/**
 * Activating a license key from the command line (`chatvia:license:activate`),
 * also with `--stdin`. The action's other answers are in
 * tests/Unit/App/Admin/Settings/Actions/ActivateLicenseActionTest.php.
 */
beforeEach(function (): void {
    LicenseKeys::trust();
    config([
        'app.url' => 'https://chat.acme.com',
        'chatvia.license.api_url' => 'https://licenses.test/api/licenses',
    ]);
});

it('activates and saves the key', function (): void {
    Http::fake(['licenses.test/api/licenses/activate' => Http::response(['key' => LicenseKeys::activatedFor('chat.acme.com')])]);

    $this->artisan('chatvia:license:activate', ['key' => LicenseKeys::BOUGHT_KEY])
        ->expectsOutput('The license is active for chat.acme.com, licensed to Acme Ltd.')
        ->assertSuccessful();

    expect(savedSetting(BrandSettings::class, 'license_key'))->toBe(LicenseKeys::activatedFor('chat.acme.com'));
});

it('fails with the reason when chatvia.ai refuses the key', function (): void {
    Http::fake(['licenses.test/api/licenses/activate' => Http::response(['message' => 'This key is active on another domain.'], 422)]);

    $this->artisan('chatvia:license:activate', ['key' => LicenseKeys::BOUGHT_KEY])
        ->expectsOutput('This key is active on another domain.')
        ->assertFailed();

    expect(savedSetting(BrandSettings::class, 'license_key'))->toBeNull();
});

it('asks for the key when it is not given', function (): void {
    Http::fake(['licenses.test/api/licenses/activate' => Http::response(['key' => LicenseKeys::activatedFor('chat.acme.com')])]);

    $this->artisan('chatvia:license:activate')
        ->expectsQuestion('License key', LicenseKeys::BOUGHT_KEY)
        ->assertSuccessful();

    expect(savedSetting(BrandSettings::class, 'license_key'))->toBe(LicenseKeys::activatedFor('chat.acme.com'));
});
