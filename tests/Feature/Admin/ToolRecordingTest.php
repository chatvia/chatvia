<?php

use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\ToolRecording;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;

/*
 * Settings → Platform turns the recording of Pulse and Spectra on and off.
 * Both tools read their own switch only while they boot, so ToolRecording
 * applies the saved choice after every settings apply. PULSE_ENABLED and
 * SPECTRA_ENABLED in .env still leave a tool out entirely. The tests boot
 * with Pulse left out (phpunit.xml), so each test sets the .env values it
 * needs and starts from a new ToolRecording, as a process that just booted.
 */

function bootedToolRecording(bool $pulse = true, bool $spectra = true): ToolRecording
{
    config(['pulse.enabled' => $pulse, 'spectra.enabled' => $spectra]);
    app()->forgetInstance(ToolRecording::class);
    Pulse::spy();

    $tools = app(ToolRecording::class);
    $tools->apply();

    return $tools;
}

it('records Pulse and not Spectra while nothing is saved', function (): void {
    bootedToolRecording();

    expect(config('spectra.enabled'))->toBeFalse();
    Pulse::shouldHaveReceived('startRecording')->once();
});

it('follows the saved switches', function (): void {
    bootedToolRecording();

    saveSettings(PlatformSettings::class, ['pulse_enabled' => false, 'spectra_enabled' => true]);

    expect(config('spectra.enabled'))->toBeTrue();
    Pulse::shouldHaveReceived('stopRecording')->once();
});

it('keeps a tool that .env leaves out from recording', function (): void {
    $tools = bootedToolRecording(pulse: false, spectra: false);

    saveSettings(PlatformSettings::class, ['pulse_enabled' => true, 'spectra_enabled' => true]);

    expect(config('spectra.enabled'))->toBeFalse()
        ->and($tools->pulseInstalled())->toBeFalse()
        ->and($tools->spectraInstalled())->toBeFalse();
    Pulse::shouldNotHaveReceived('startRecording');
});

it('leaves Pulse alone when another setting is saved', function (): void {
    bootedToolRecording();

    // The demo seeders stop Pulse while they run; a save must not start it.
    saveSettings(PlatformSettings::class, ['tracking_code' => '<script></script>']);

    // Only the start at boot.
    Pulse::shouldHaveReceived('startRecording')->once();
    Pulse::shouldNotHaveReceived('stopRecording');
});

it('turns Spectra on in a queue worker before its next job', function (): void {
    // The worker booted while Spectra was switched off.
    bootedToolRecording();
    saveSettings(PlatformSettings::class, ['spectra_enabled' => false]);

    // Another process saved the switch: this one has not seen it yet.
    DB::table('settings')->where('group', 'platform')->where('name', 'spectra_enabled')
        ->update(['payload' => json_encode(true)]);
    cache()->flush();
    app()->forgetScopedInstances();

    dispatch(function (): void {
        cache()->put('spectra-seen-by-job', config('spectra.enabled'));
    });

    expect(cache('spectra-seen-by-job'))->toBeTrue();
});
