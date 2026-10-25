<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\PlatformSettings;

/**
 * Settings → Platform: the tracking code of the app shell.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('keeps the tracking code as typed, and an empty box turns it off', function (): void {
    $snippet = "<script defer data-site=\"acme\" src=\"https://stats.acme.example/js/script.js\"></script>\n<script>window.stats = window.stats || [];</script>";

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['tracking_code' => $snippet])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(PlatformSettings::class, 'tracking_code'))->toBe($snippet)
        ->and(config('chatvia.tracking_code'))->toBe($snippet);

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->assertSchemaStateSet(['tracking_code' => $snippet])
        ->fillForm(['tracking_code' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(PlatformSettings::class, 'tracking_code'))->toBeNull();
});
