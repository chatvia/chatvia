<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\PlatformSettings;

/**
 * Settings → Platform: the app's name and the tracking code of the app shell.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('names the app in the panel and the page titles, and a blank name is ChatVia.ai again', function (): void {
    $this->actingAs($this->admin, 'admin')->get(ManagePlatformSettings::getUrl())
        ->assertSeeInOrder(['<title>', 'Platform', 'ChatVia.ai Admin', '</title>'], false);

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['app_name' => 'Acme Support'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(PlatformSettings::class, 'app_name'))->toBe('Acme Support')
        ->and(config('app.name'))->toBe('Acme Support');

    $this->actingAs($this->admin, 'admin')->get(ManagePlatformSettings::getUrl())
        ->assertSeeInOrder(['<title>', 'Platform', 'Acme Support Admin', '</title>'], false)
        ->assertSee('<span class="cv-brand__name">Acme Support</span>', false);

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['app_name' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(PlatformSettings::class, 'app_name'))->toBeNull()
        ->and(config('app.name'))->toBe('ChatVia.ai');
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
