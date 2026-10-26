<?php

use App\Admin\Clusters\Settings\SettingsCluster;
use App\Admin\Pages\Dashboard;
use App\Admin\Pages\License;
use App\Admin\Settings\BrandSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\License\LicenseKeys;

/**
 * License: hiding the "Powered by" credit needs a license key. A new
 * key is activated at chatvia.ai for this install's domain before it is
 * saved, and a removed key is freed there again. The page has its own place
 * in the sidebar, below Settings.
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

it('lists the page in the sidebar below Settings, outside it', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['cv-sidebar-group--divided', 'href="'.SettingsCluster::getUrl().'"', 'href="'.License::getUrl().'"'], false);

    $this->actingAs($this->admin, 'admin')
        ->get(License::getUrl())
        ->assertOk()
        ->assertSee('Powered by');

    expect(SettingsCluster::getClusteredComponents())->not->toContain(License::class)
        ->and(License::getUrl())->toEndWith('/admin/license');
});

it('activates a bought key for this domain, then lets the credit be hidden', function (): void {
    Http::fake(['licenses.test/api/licenses/activate' => Http::response(['key' => LicenseKeys::activatedFor('chat.acme.com')])]);

    adminPage(License::class, $this->admin)
        ->assertFormFieldDisabled('powered_by_enabled')
        ->fillForm(['license_key' => LicenseKeys::BOUGHT_KEY])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Settings saved')
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

it('shows why chatvia.ai refused the key and saves nothing', function (): void {
    Http::fake(['licenses.test/*' => Http::response(['message' => 'This key is already active on another domain.'], 409)]);

    adminPage(License::class, $this->admin)
        ->fillForm(['license_key' => LicenseKeys::BOUGHT_KEY])
        ->call('save')
        ->assertHasFormErrors(['license_key' => 'This key is already active on another domain.']);

    expect(licSaved()->license_key)->toBeNull();
});

it('says so when chatvia.ai cannot be reached', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    adminPage(License::class, $this->admin)
        ->fillForm(['license_key' => LicenseKeys::BOUGHT_KEY])
        ->call('save')
        ->assertHasFormErrors(['license_key' => 'chatvia.ai could not be reached to activate the key. Try again in a few minutes.']);
});

it('refuses text that is not a key without asking chatvia.ai', function (): void {
    Http::fake();

    adminPage(License::class, $this->admin)
        ->fillForm(['license_key' => 'not-a-license-key'])
        ->call('save')
        ->assertHasFormErrors(['license_key' => 'This is not a valid license key. Check that you copied all of it.']);

    Http::assertNothingSent();
});

it('greys out the switch and keeps the credit on without a license', function (): void {
    adminPage(License::class, $this->admin)
        ->assertFormFieldDisabled('powered_by_enabled')
        ->assertSee('Add a license key below to hide the credit.')
        ->fillForm(['powered_by_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(licSaved()->powered_by_enabled)->toBeNull()
        ->and(config('chatvia.powered_by'))->toBeTrue();
});

it('shows the switch on, greyed out, when the saved license stopped working', function (): void {
    $settings = licSaved();
    $settings->powered_by_enabled = false;
    $settings->license_key = LicenseKeys::activatedFor('chat.moved.com');
    $settings->save();

    adminPage(License::class, $this->admin)
        ->assertFormFieldDisabled('powered_by_enabled')
        ->assertSchemaStateSet(['powered_by_enabled' => true])
        ->assertSee('The license is not active here');
});

it('shows who the saved key is licensed to', function (): void {
    $settings = licSaved();
    $settings->license_key = LicenseKeys::activatedFor('chat.acme.com');
    $settings->save();

    adminPage(License::class, $this->admin)
        ->assertSee('Pro license, licensed to Acme Ltd')
        ->assertSee('Manage license')
        ->assertDontSee('Buy a license')
        ->assertDontSee(LicenseKeys::activatedFor('chat.acme.com'));
});

it('says when the saved key is an Enterprise license', function (): void {
    $settings = licSaved();
    $settings->license_key = LicenseKeys::enterpriseFor('chat.acme.com');
    $settings->save();

    adminPage(License::class, $this->admin)
        ->assertSee('Enterprise license, licensed to Acme Ltd')
        ->assertSee('the Enterprise features are on');
});

it('offers to buy or manage a license while there is none', function (): void {
    adminPage(License::class, $this->admin)
        ->assertSee('Hiding the credit needs a license')
        ->assertSee('A Pro license costs $399, one time, and never expires.')
        ->assertSee('Buy a license')
        ->assertSee('Manage license');
});

it('frees a removed key at chatvia.ai and shows the credit again', function (): void {
    Http::fake(['licenses.test/api/licenses/deactivate' => Http::response(['released' => true])]);

    $settings = licSaved();
    $settings->powered_by_enabled = false;
    $settings->license_key = LicenseKeys::activatedFor('chat.acme.com');
    $settings->save();

    expect(config('chatvia.powered_by'))->toBeFalse();

    adminPage(License::class, $this->admin)
        ->callAction(TestAction::make('forget_license_key')->schemaComponent('license_key', schema: 'form'));

    expect(licSaved()->license_key)->toBeNull()
        ->and(licSaved()->powered_by_enabled)->toBeNull()
        ->and(config('chatvia.powered_by'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://licenses.test/api/licenses/deactivate'
        && $request['key'] === LicenseKeys::activatedFor('chat.acme.com')
        && $request['domain'] === 'chat.acme.com');
});
