<?php

use App\Admin\Clusters\Settings\Pages\ManageBrandSettings;
use App\Admin\Settings\BrandSettings;
use App\Identity\Workspace\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\LicenseKeys;

/**
 * Settings → Branding: hiding the "Powered by" credit needs a license key. A new
 * key is activated at chatvia.ai for this install's domain before it is
 * saved, and a removed key is freed there again.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();

    LicenseKeys::trust();
    config([
        'app.url' => 'https://chat.acme.com',
        'chatvia.license.api_url' => 'https://licenses.test/api/licenses',
    ]);
});

function brandPage(User $admin): Testable
{
    return Livewire::actingAs($admin, 'admin')->test(ManageBrandSettings::class);
}

function savedBrand(): BrandSettings
{
    app()->forgetScopedInstances();

    return app(BrandSettings::class);
}

it('activates a bought key for this domain, then lets the credit be hidden', function (): void {
    Http::fake(['licenses.test/api/licenses/activate' => Http::response(['key' => LicenseKeys::activatedFor('chat.acme.com')])]);

    brandPage($this->admin)
        ->assertFormFieldDisabled('powered_by_enabled')
        ->fillForm(['license_key' => LicenseKeys::BOUGHT_KEY])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Settings saved')
        ->assertFormFieldEnabled('powered_by_enabled')
        ->fillForm(['powered_by_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedBrand()->license_key)->toBe(LicenseKeys::activatedFor('chat.acme.com'))
        ->and(savedBrand()->powered_by_enabled)->toBeFalse()
        ->and(config('chatvia.brand.powered_by.enabled'))->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://licenses.test/api/licenses/activate'
        && $request['key'] === LicenseKeys::BOUGHT_KEY
        && $request['domain'] === 'chat.acme.com');
});

it('shows why chatvia.ai refused the key and saves nothing', function (): void {
    Http::fake(['licenses.test/*' => Http::response(['message' => 'This key is already active on another domain.'], 409)]);

    brandPage($this->admin)
        ->fillForm(['license_key' => LicenseKeys::BOUGHT_KEY])
        ->call('save')
        ->assertHasFormErrors(['license_key' => 'This key is already active on another domain.']);

    expect(savedBrand()->license_key)->toBeNull();
});

it('says so when chatvia.ai cannot be reached', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    brandPage($this->admin)
        ->fillForm(['license_key' => LicenseKeys::BOUGHT_KEY])
        ->call('save')
        ->assertHasFormErrors(['license_key' => 'chatvia.ai could not be reached to activate the key. Try again in a few minutes.']);
});

it('refuses text that is not a key without asking chatvia.ai', function (): void {
    Http::fake();

    brandPage($this->admin)
        ->fillForm(['license_key' => 'not-a-license-key'])
        ->call('save')
        ->assertHasFormErrors(['license_key' => 'This is not a valid license key. Check that you copied all of it.']);

    Http::assertNothingSent();
});

it('greys out the switch and keeps the credit on without a license', function (): void {
    brandPage($this->admin)
        ->assertFormFieldDisabled('powered_by_enabled')
        ->assertSee('Add a license key below to turn the credit off.')
        ->fillForm(['powered_by_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedBrand()->powered_by_enabled)->toBeNull()
        ->and(config('chatvia.brand.powered_by.enabled'))->toBeTrue();
});

it('shows the switch on, greyed out, when the saved license stopped working', function (): void {
    $settings = savedBrand();
    $settings->powered_by_enabled = false;
    $settings->license_key = LicenseKeys::activatedFor('chat.moved.com');
    $settings->save();

    brandPage($this->admin)
        ->assertFormFieldDisabled('powered_by_enabled')
        ->assertSchemaStateSet(['powered_by_enabled' => true])
        ->assertSee('The license is not active here');
});

it('shows who the saved key is licensed to', function (): void {
    $settings = savedBrand();
    $settings->license_key = LicenseKeys::activatedFor('chat.acme.com');
    $settings->save();

    brandPage($this->admin)
        ->assertSee('Licensed to Acme Ltd')
        ->assertSee('Manage license')
        ->assertDontSee('Buy a license')
        ->assertDontSee(LicenseKeys::activatedFor('chat.acme.com'));
});

it('offers to buy or manage a license while there is none', function (): void {
    brandPage($this->admin)
        ->assertSee('Hiding the credit needs a license')
        ->assertSee('A license costs $399, one time, and never expires.')
        ->assertSee('Buy a license')
        ->assertSee('Manage license');
});

it('frees a removed key at chatvia.ai and shows the credit again', function (): void {
    Http::fake(['licenses.test/api/licenses/deactivate' => Http::response(['released' => true])]);

    $settings = savedBrand();
    $settings->powered_by_enabled = false;
    $settings->license_key = LicenseKeys::activatedFor('chat.acme.com');
    $settings->save();

    expect(config('chatvia.brand.powered_by.enabled'))->toBeFalse();

    brandPage($this->admin)
        ->callAction(TestAction::make('forget_license_key')->schemaComponent('license_key', schema: 'form'));

    expect(savedBrand()->license_key)->toBeNull()
        ->and(savedBrand()->powered_by_enabled)->toBeNull()
        ->and(config('chatvia.brand.powered_by.enabled'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://licenses.test/api/licenses/deactivate'
        && $request['key'] === LicenseKeys::activatedFor('chat.acme.com')
        && $request['domain'] === 'chat.acme.com');
});

it('offers the developer\'s services', function (): void {
    brandPage($this->admin)
        ->assertSee('Hire me')
        ->assertSee('See my services')
        ->assertSee('https://mayahi.net/services');
});
