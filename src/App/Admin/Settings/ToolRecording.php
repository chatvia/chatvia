<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Illuminate\Contracts\Config\Repository as Config;
use Laravel\Pulse\Facades\Pulse;

/**
 * Turns the recording of two staff tools on and off from Settings →
 * Observability: Pulse (the app's health) and Spectra (the AI call log).
 *
 * Both read their own `enabled` flag once, while they boot, which is before
 * the saved settings are applied. So that flag stays the "installed" switch
 * (PULSE_ENABLED, SPECTRA_ENABLED in .env), and this decides whether an
 * installed tool records: Pulse through its recording switch, Spectra
 * through `spectra.enabled`, which it reads again on every AI call. Their
 * dashboards stay open either way, so what was recorded can still be read.
 * {@see SettingsConfig} runs this after every apply, so a queue worker
 * follows a change before its next job.
 */
final class ToolRecording
{
    private ?bool $pulseInstalled = null;

    private ?bool $spectraInstalled = null;

    private ?bool $pulseRecording = null;

    public function __construct(
        private readonly Config $config,
    ) {}

    public function apply(): void
    {
        // The first apply comes right after the tools booted, while these
        // keys still hold the .env values.
        $this->pulseInstalled ??= (bool) $this->config->get('pulse.enabled');
        $this->spectraInstalled ??= (bool) $this->config->get('spectra.enabled');

        $this->config->set('spectra.enabled', $this->spectraInstalled && (bool) $this->config->get('chatvia.spectra.recording'));

        $pulse = $this->pulseInstalled && (bool) $this->config->get('chatvia.pulse.recording');

        // Only a change flips Pulse: the demo seeders stop it while they run,
        // and saving another setting must not start it again.
        if ($pulse !== $this->pulseRecording) {
            $pulse ? Pulse::startRecording() : Pulse::stopRecording();
            $this->pulseRecording = $pulse;
        }
    }

    /** Whether Pulse booted with this install (PULSE_ENABLED). */
    public function pulseInstalled(): bool
    {
        return $this->pulseInstalled ?? (bool) $this->config->get('pulse.enabled');
    }

    /** Whether Spectra booted with this install (SPECTRA_ENABLED). */
    public function spectraInstalled(): bool
    {
        return $this->spectraInstalled ?? (bool) $this->config->get('spectra.enabled');
    }
}
