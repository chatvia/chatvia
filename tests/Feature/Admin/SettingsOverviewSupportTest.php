<?php

use App\Admin\Clusters\Settings\Pages\SettingsOverview;
use App\Admin\Settings\BrandSettings;
use Tests\Support\LicenseKeys;

/**
 * The Settings overview asks the install to buy a license (support plus no
 * "Powered by" credit) and thanks the install that has one.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    LicenseKeys::trust();
    config(['app.url' => 'https://chat.acme.com']);
});

it('asks an install without a license for support', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(SettingsOverview::getUrl())
        ->assertOk()
        ->assertSee('Support ChatVia.ai')
        ->assertSee('Buy a license')
        ->assertSee('Hire me')
        ->assertSee('https://mayahi.net/services')
        ->assertDontSee('Thank you for your support');
});

it('thanks an install with an active license', function (): void {
    $settings = app(BrandSettings::class);
    $settings->license_key = LicenseKeys::activatedFor('chat.acme.com');
    $settings->save();

    $this->actingAs($this->admin, 'admin')
        ->get(SettingsOverview::getUrl())
        ->assertOk()
        ->assertSee('Thank you for your support')
        ->assertSee('licensed to Acme Ltd')
        ->assertDontSee('Support ChatVia.ai');
});
