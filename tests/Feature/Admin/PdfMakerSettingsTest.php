<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\PlatformSettings;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Settings → Platform → PDF maker: WeasyPrint on this server, or Cloudflare
 * Browser Rendering or a Gotenberg server for a host that cannot install
 * programs. A new pick is saved only after it made a test PDF; keys are
 * write-only, and the PDF drivers are built again with the new values.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');
});

it('shows only the settings of the picked maker', function (): void {
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->assertFormSet(['pdf_maker' => 'weasyprint'])
        ->assertFormFieldHidden('pdf_cloudflare_token')
        ->assertFormFieldHidden('pdf_gotenberg_url')
        ->fillForm(['pdf_maker' => 'cloudflare'])
        ->assertFormFieldVisible('pdf_cloudflare_account_id')
        ->assertFormFieldVisible('pdf_cloudflare_token')
        ->assertFormFieldHidden('pdf_gotenberg_url')
        ->fillForm(['pdf_maker' => 'gotenberg'])
        ->assertFormFieldVisible('pdf_gotenberg_url')
        ->assertFormFieldHidden('pdf_cloudflare_token');
});

it('saves Cloudflare after a test PDF, and uses it at once', function (): void {
    Http::fake(['api.cloudflare.com/*' => Http::response('%PDF-1.7 test', 200)]);
    $driver = app('laravel-pdf.driver.weasyprint');

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['pdf_maker' => 'cloudflare', 'pdf_cloudflare_account_id' => 'account-1', 'pdf_cloudflare_token' => 'token-1'])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetScopedInstances();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/accounts/account-1/browser-rendering/pdf'));

    expect(config('laravel-pdf.driver'))->toBe('cloudflare')
        ->and(config('laravel-pdf.cloudflare'))->toBe(['api_token' => 'token-1', 'account_id' => 'account-1'])
        ->and(DB::table('settings')->where('group', 'platform')->where('name', 'pdf_cloudflare_token')->value('payload'))->not->toContain('token-1')
        // A queue worker built the driver once; it is built again.
        ->and(app('laravel-pdf.driver.cloudflare'))->not->toBeNull()
        ->and(app('laravel-pdf.driver.weasyprint'))->not->toBe($driver);

    // The saved token is never sent back to the browser.
    adminPage(ManagePlatformSettings::class, $this->admin)->assertFormSet(['pdf_maker' => 'cloudflare', 'pdf_cloudflare_token' => null]);
});

it('does not save a Cloudflare token the service refuses', function (): void {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false], 403)]);

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['pdf_maker' => 'cloudflare', 'pdf_cloudflare_account_id' => 'account-1', 'pdf_cloudflare_token' => 'wrong'])
        ->call('save')
        ->assertHasFormErrors(['pdf_maker']);

    expect(app(PlatformSettings::class)->pdf_maker)->toBeNull()
        ->and(config('laravel-pdf.driver'))->toBe('weasyprint');
});

it('asks for the account ID and token of Cloudflare', function (): void {
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['pdf_maker' => 'cloudflare'])
        ->call('save')
        ->assertHasFormErrors(['pdf_cloudflare_account_id' => 'required', 'pdf_cloudflare_token' => 'required']);
});

it('saves a Gotenberg server, and makes no test PDF when nothing changed', function (): void {
    Http::fake(['gotenberg.test/*' => Http::response('%PDF-1.7 test', 200)]);
    $driver = app('laravel-pdf.driver.gotenberg');

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['pdf_maker' => 'gotenberg', 'pdf_gotenberg_url' => 'http://gotenberg.test'])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetScopedInstances();

    adminPage(ManagePlatformSettings::class, $this->admin)->call('save')->assertHasNoFormErrors();

    Http::assertSentCount(1);

    expect(config('laravel-pdf.driver'))->toBe('gotenberg')
        ->and(config('laravel-pdf.gotenberg.url'))->toBe('http://gotenberg.test')
        ->and(app('laravel-pdf.driver.gotenberg'))->not->toBe($driver);
});

it('does not save a Gotenberg server that makes no PDF', function (): void {
    Http::fake(['gotenberg.test/*' => Http::response('Bad gateway', 502)]);

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['pdf_maker' => 'gotenberg', 'pdf_gotenberg_url' => 'http://gotenberg.test'])
        ->call('save')
        ->assertHasFormErrors(['pdf_maker']);

    expect(config('laravel-pdf.driver'))->toBe('weasyprint');
});

it('warns on the card when WeasyPrint is not found', function (): void {
    saveSettings(PlatformSettings::class, ['weasyprint_path' => '/nowhere/weasyprint']);

    adminPage(ManagePlatformSettings::class, $this->admin)->assertSee('WeasyPrint · not found on this server');
});
