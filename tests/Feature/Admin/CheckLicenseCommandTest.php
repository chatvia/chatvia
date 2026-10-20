<?php

use App\Admin\Settings\BrandSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\LicenseKeys;

/**
 * The daily license check from the command line (`chatvia:license:check`): a key
 * that chatvia.ai no longer has active for this domain is removed and the
 * credit shows again. The action's other answers are in
 * tests/Unit/Admin/Settings/CheckLicenseActionTest.php.
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

it('removes a key that was deactivated at chatvia.ai', function (): void {
    Http::fake(['licenses.test/api/licenses/check' => Http::response(['active' => false])]);

    $this->artisan('chatvia:license:check')->assertSuccessful();

    expect(savedSetting(BrandSettings::class, 'license_key'))->toBeNull()
        ->and(app(BrandSettings::class)->powered_by_enabled)->toBeNull()
        ->and(config('chatvia.powered_by'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request['id'] === LicenseKeys::ID
        && $request['domain'] === 'chat.acme.com'
        && ! isset($request['key']));
});
