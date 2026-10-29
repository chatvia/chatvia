<?php

use App\Admin\Clusters\Settings\Pages\ManageObservabilitySettings;
use App\Admin\Settings\ObservabilitySettings;
use App\Admin\Settings\ToolRecording;
use Laravel\Pulse\Support\CacheStoreResolver;

/**
 * Settings → Observability: the Spectra switch stops recording AI calls at
 * once, and changing the Pulse switch restarts Pulse's server stats.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('stops recording when the Spectra switch is turned off', function (): void {
    config(['chatvia.spectra.recording' => true]);

    adminPage(ManageObservabilitySettings::class, $this->admin)
        ->fillForm(['spectra_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(ObservabilitySettings::class, 'spectra_enabled'))->toBeFalse()
        ->and(config('chatvia.spectra.recording'))->toBeFalse()
        ->and(config('spectra.enabled'))->toBeFalse();
});

it('restarts Pulse\'s server stats only when the Pulse switch changes', function (): void {
    config(['pulse.enabled' => true]);
    app()->forgetInstance(ToolRecording::class);
    $this->freezeTime();
    $signal = fn (): mixed => app(CacheStoreResolver::class)->store()->get('laravel:pulse:restart');

    adminPage(ManageObservabilitySettings::class, $this->admin)
        ->fillForm(['spectra_retention_days' => 14])
        ->call('save');

    expect($signal())->toBeNull();

    adminPage(ManageObservabilitySettings::class, $this->admin)
        ->fillForm(['pulse_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(ObservabilitySettings::class, 'pulse_enabled'))->toBeFalse()
        ->and($signal())->toBe(now()->getTimestamp());
});
