<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\PlatformSettings;

/**
 * Settings → Platform: where the chat widget's script comes from, and the
 * tracking code of the app shell.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('takes a path on this server or a full address for the widget script', function (string $address): void {
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['widget_script_url' => $address])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(PlatformSettings::class, 'widget_script_url'))->toBe($address)
        ->and(url(config('chatvia.widget.script_url_embed')))->toBe(url($address));
})->with([
    'a path on this server' => '/shakomako/next/embed.js',
    'a full address' => 'https://cdn.example.com/embed.js',
]);

it('refuses a widget script address that is neither', function (string $address): void {
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['widget_script_url' => $address])
        ->call('save')
        ->assertHasFormErrors(['widget_script_url']);

    expect(savedSetting(PlatformSettings::class, 'widget_script_url'))->toBeNull();
})->with([
    'a relative path' => 'shakomako/embed.js',
    'an address without a scheme' => '//cdn.example.com/embed.js',
    'a script' => 'javascript:alert(1)',
    'another scheme' => 'ftp://cdn.example.com/embed.js',
]);

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
