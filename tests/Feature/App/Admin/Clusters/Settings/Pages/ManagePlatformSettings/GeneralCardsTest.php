<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\ToolRecording;
use Illuminate\Support\Facades\Artisan;

/**
 * Settings → Platform: where the chat widget's script comes from, the
 * tracking code of the app shell, and the Pulse and Spectra switches.
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

it('keeps Spectra on, and keeps only counts while prompts and replies are not saved', function (): void {
    // phpunit.xml leaves Pulse out of the install, and Spectra in. Spectra
    // records out of the box, so its switch starts on.
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->assertSee(['Off in .env', 'PULSE_ENABLED=false in .env', 'Open Spectra'])
        // A number left blank shows its default (30 days) as a placeholder.
        ->assertSchemaStateSet(['spectra_enabled' => true, 'spectra_store_content' => true, 'spectra_retention_days' => null])
        ->fillForm(['spectra_enabled' => true, 'spectra_store_content' => false, 'spectra_retention_days' => 7])
        ->call('save')
        ->assertHasNoFormErrors();

    // On is the default, so the switch stores nothing.
    expect(savedSetting(PlatformSettings::class, 'spectra_enabled'))->toBeNull()
        ->and(config('spectra.enabled'))->toBeTrue()
        ->and(config('spectra.storage.store_prompts'))->toBeFalse()
        ->and(config('spectra.storage.store_responses'))->toBeFalse()
        ->and(config('spectra.storage.store_system_prompts'))->toBeFalse()
        ->and(config('chatvia.spectra.retention_days'))->toBe(7);
});

it('restarts Pulse\'s server stats when the Pulse switch changes', function (): void {
    // As if PULSE_ENABLED were true when the app booted.
    config(['pulse.enabled' => true]);
    app()->forgetInstance(ToolRecording::class);
    Artisan::spy();

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['tracking_code' => '<script></script>'])
        ->call('save');

    Artisan::shouldNotHaveReceived('call', ['pulse:restart']);

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->assertSchemaStateSet(['pulse_enabled' => true])
        ->fillForm(['pulse_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(PlatformSettings::class, 'pulse_enabled'))->toBeFalse();
    Artisan::shouldHaveReceived('call')->with('pulse:restart')->once();
});
