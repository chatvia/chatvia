<?php

use App\Admin\Settings\ObservabilitySettings;
use App\Admin\Settings\ToolRecording;
use Laravel\Pulse\Facades\Pulse;

/**
 * Settings → Observability turns the recording of Pulse and Spectra on and
 * off.
 * Both tools read their own switch only while they boot, so ToolRecording
 * applies the saved choice after every settings apply. PULSE_ENABLED and
 * SPECTRA_ENABLED in .env still leave a tool out entirely. The tests boot
 * with Pulse left out (phpunit.xml), so each test sets the .env values it
 * needs and starts from a new ToolRecording, as a process that just booted.
 * That a queue worker applies a change before its next job is
 * AdminSettingsServiceProviderTest's.
 */
function trBooted(bool $pulse = true, bool $spectra = true): ToolRecording
{
    config(['pulse.enabled' => $pulse, 'spectra.enabled' => $spectra]);
    app()->forgetInstance(ToolRecording::class);
    Pulse::spy();

    $tools = app(ToolRecording::class);
    $tools->apply();

    return $tools;
}

it('stops Pulse and keeps Spectra recording as the saved switches say', function (): void {
    trBooted();

    saveSettings(ObservabilitySettings::class, ['pulse_enabled' => false, 'spectra_enabled' => true]);

    expect(config('spectra.enabled'))->toBeTrue();
    Pulse::shouldHaveReceived('stopRecording')->once();
});

it('keeps a tool that .env leaves out from recording', function (): void {
    $tools = trBooted(pulse: false, spectra: false);

    saveSettings(ObservabilitySettings::class, ['pulse_enabled' => true, 'spectra_enabled' => true]);

    expect(config('spectra.enabled'))->toBeFalse()
        ->and($tools->pulseInstalled())->toBeFalse()
        ->and($tools->spectraInstalled())->toBeFalse();
    Pulse::shouldNotHaveReceived('startRecording');
});
