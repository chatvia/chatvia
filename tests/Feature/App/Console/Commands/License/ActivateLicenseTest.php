<?php

use App\Admin\Settings\BrandSettings;
use Illuminate\Support\Facades\Http;
use Tests\Support\License\LicenseKeys;

/**
 * Activating a license key from the command line (`chatvia:license:activate`):
 * an accepted key is saved, a refused one fails and saves nothing. The action's other answers are in
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
        ->assertSuccessful();

    expect(savedSetting(BrandSettings::class, 'license_key'))->toBe(LicenseKeys::activatedFor('chat.acme.com'));
});

it('fails with the reason when chatvia.ai refuses the key', function (): void {
    Http::fake(['licenses.test/api/licenses/activate' => Http::response(['message' => 'This key is active on another domain.'], 422)]);

    $this->artisan('chatvia:license:activate', ['key' => LicenseKeys::BOUGHT_KEY])
        ->assertFailed();

    expect(savedSetting(BrandSettings::class, 'license_key'))->toBeNull();
});
