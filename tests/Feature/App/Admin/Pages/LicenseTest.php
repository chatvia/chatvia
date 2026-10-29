<?php

use App\Admin\Pages\License;
use App\Admin\Settings\BrandSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\License\LicenseKeys;

/**
 * License: hiding the "Powered by" credit needs a license key. A new key is
 * activated at chatvia.ai for this install's domain before it is saved, a
 * key chatvia.ai refuses saves nothing, and without a key the credit stays
 * on whatever the switch says.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    LicenseKeys::trust();
    config([
        'app.url' => 'https://chat.acme.com',
        'chatvia.license.api_url' => 'https://licenses.test/api/licenses',
    ]);
});

function licSaved(): BrandSettings
{
    app()->forgetScopedInstances();

    return app(BrandSettings::class);
}

it('activates a bought key for this domain, then lets the credit be hidden', function (): void {
    Http::fake(['licenses.test/api/licenses/activate' => Http::response(['key' => LicenseKeys::activatedFor('chat.acme.com')])]);

    adminPage(License::class, $this->admin)
        ->assertFormFieldDisabled('powered_by_enabled')
        ->fillForm(['license_key' => LicenseKeys::BOUGHT_KEY])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertFormFieldEnabled('powered_by_enabled')
        ->fillForm(['powered_by_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(licSaved()->license_key)->toBe(LicenseKeys::activatedFor('chat.acme.com'))
        ->and(licSaved()->powered_by_enabled)->toBeFalse()
        ->and(config('chatvia.powered_by'))->toBeFalse();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://licenses.test/api/licenses/activate'
        && $request['key'] === LicenseKeys::BOUGHT_KEY
        && $request['domain'] === 'chat.acme.com');
});

it('saves nothing when chatvia.ai refuses the key', function (): void {
    Http::fake(['licenses.test/*' => Http::response(['message' => 'This key is already active on another domain.'], 409)]);

    adminPage(License::class, $this->admin)
        ->fillForm(['license_key' => LicenseKeys::BOUGHT_KEY])
        ->call('save')
        ->assertHasFormErrors(['license_key']);

    expect(licSaved()->license_key)->toBeNull();
});

it('keeps the credit on without a license, even when the switch is turned off', function (): void {
    adminPage(License::class, $this->admin)
        ->assertFormFieldDisabled('powered_by_enabled')
        ->fillForm(['powered_by_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(licSaved()->powered_by_enabled)->toBeNull()
        ->and(config('chatvia.powered_by'))->toBeTrue();
});
