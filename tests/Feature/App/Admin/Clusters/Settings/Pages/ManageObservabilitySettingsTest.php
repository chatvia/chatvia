<?php

use App\Admin\Clusters\Settings\Pages\ManageObservabilitySettings;
use App\Admin\Settings\ObservabilitySettings;
use App\Admin\Settings\ToolRecording;
use Illuminate\Support\Facades\Artisan;

/**
 * Settings → Observability: whether Pulse records the app's health, and
 * whether Spectra records AI calls, what it saves of each one, how long it
 * keeps them, and how it groups them into traces. Spectra reads its keys on
 * every call, so a save applies at once.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('keeps Spectra on, and keeps only counts while nothing else is saved', function (): void {
    // Spectra records out of the box, so its switch starts on.
    config(['chatvia.spectra.recording' => true]);

    adminPage(ManageObservabilitySettings::class, $this->admin)
        ->assertSee(['Open Spectra', 'What Spectra saves', 'Traces'])
        // A number left blank shows its default (30 days) as a placeholder.
        ->assertSchemaStateSet([
            'spectra_enabled' => true,
            'spectra_retention_days' => null,
            'spectra_store_prompts' => true,
            'spectra_store_request_data' => true,
            'spectra_store_embeddings' => false,
            'spectra_trace_per_request' => true,
        ])
        ->fillForm([
            'spectra_enabled' => true,
            'spectra_retention_days' => 7,
            'spectra_store_prompts' => false,
            'spectra_store_system_prompts' => false,
            'spectra_store_responses' => false,
            'spectra_store_tools' => false,
            'spectra_store_request_data' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSee('Counts, costs and times only');

    // On is the default, so the switch stores nothing.
    expect(savedSetting(ObservabilitySettings::class, 'spectra_enabled'))->toBeNull()
        ->and(config('spectra.enabled'))->toBeTrue()
        ->and(config('spectra.storage.store_prompts'))->toBeFalse()
        ->and(config('spectra.storage.store_system_prompts'))->toBeFalse()
        ->and(config('spectra.storage.store_responses'))->toBeFalse()
        ->and(config('spectra.storage.store_tools'))->toBeFalse()
        ->and(config('spectra.storage.store_request_data'))->toBeFalse()
        ->and(config('chatvia.spectra.retention_days'))->toBe(7);
});

it('stops recording when the switch is turned off', function (): void {
    config(['chatvia.spectra.recording' => true]);

    adminPage(ManageObservabilitySettings::class, $this->admin)
        ->fillForm(['spectra_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(ObservabilitySettings::class, 'spectra_enabled'))->toBeFalse()
        ->and(config('chatvia.spectra.recording'))->toBeFalse()
        ->and(config('spectra.enabled'))->toBeFalse();
});

it('saves embeddings and changes the traces', function (): void {
    adminPage(ManageObservabilitySettings::class, $this->admin)
        ->fillForm(['spectra_store_embeddings' => true, 'spectra_trace_per_request' => false, 'spectra_use_traceparent' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('spectra.storage.store_embeddings'))->toBeTrue()
        ->and(config('spectra.tracking.trace_per_request'))->toBeFalse()
        ->and(config('spectra.tracking.use_traceparent'))->toBeFalse();
});

it('locks the switch while .env leaves Spectra out', function (): void {
    // As if SPECTRA_ENABLED were false when the app booted.
    config(['spectra.enabled' => false]);
    app()->forgetInstance(ToolRecording::class);

    adminPage(ManageObservabilitySettings::class, $this->admin)
        ->assertSee(['Off in .env', 'SPECTRA_ENABLED=false, which leaves this tool out'])
        ->assertFormFieldDisabled('spectra_enabled');
});

it('locks the Pulse switch while .env leaves Pulse out', function (): void {
    // phpunit.xml leaves Pulse out of the install.
    adminPage(ManageObservabilitySettings::class, $this->admin)
        ->assertSee(['PULSE_ENABLED=false, which leaves this tool out'])
        ->assertFormFieldDisabled('pulse_enabled');
});

it('restarts Pulse\'s server stats when the Pulse switch changes', function (): void {
    // As if PULSE_ENABLED were true when the app booted.
    config(['pulse.enabled' => true]);
    app()->forgetInstance(ToolRecording::class);
    Artisan::spy();

    adminPage(ManageObservabilitySettings::class, $this->admin)
        ->fillForm(['spectra_retention_days' => 14])
        ->call('save');

    Artisan::shouldNotHaveReceived('call', ['pulse:restart']);

    adminPage(ManageObservabilitySettings::class, $this->admin)
        ->assertSee('Open Pulse')
        ->assertSchemaStateSet(['pulse_enabled' => true])
        ->fillForm(['pulse_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(ObservabilitySettings::class, 'pulse_enabled'))->toBeFalse();
    Artisan::shouldHaveReceived('call')->with('pulse:restart')->once();
});
