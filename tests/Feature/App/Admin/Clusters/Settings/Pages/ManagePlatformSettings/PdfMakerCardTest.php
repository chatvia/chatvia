<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\PlatformSettings;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Settings → Platform → PDF maker: a new pick is saved only after it made a
 * test PDF, its key is write-only, and the PDF drivers are built again with
 * the new values.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');
});

it('saves Cloudflare after a test PDF, uses it at once and never sends the token back', function (): void {
    Http::fake(['api.cloudflare.com/*' => Http::response('%PDF-1.7 test', 200)]);
    $driver = app('laravel-pdf.driver.weasyprint');

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['pdf_maker' => 'cloudflare', 'pdf_cloudflare_account_id' => 'account-1', 'pdf_cloudflare_token' => 'token-1'])
        ->call('save')
        ->assertHasNoFormErrors();
    app()->forgetScopedInstances();

    Http::assertSent(fn (Request $request): bool => Str::contains($request->url(), '/accounts/account-1/browser-rendering/pdf'));
    expect(config('laravel-pdf.driver'))->toBe('cloudflare')
        ->and(config('laravel-pdf.cloudflare'))->toBe(['api_token' => 'token-1', 'account_id' => 'account-1'])
        ->and(app('laravel-pdf.driver.cloudflare'))->not->toBeNull()
        ->and(app('laravel-pdf.driver.weasyprint'))->not->toBe($driver);
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
