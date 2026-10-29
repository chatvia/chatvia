<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * The staff tools that watch the install. Pulse, the app's health:
 * whether it records. Spectra, the log of every AI call: whether it
 * records, what it saves of each call besides the counts, costs and times,
 * how long it keeps them, and how calls are grouped into traces. Issues,
 * the issues with outside services an admin can fix: whether they are
 * kept, and for how long.
 *
 * Spectra reads its keys again on every AI call, so a save applies at
 * once. Whether either tool records goes through {@see ToolRecording},
 * because both read their own `enabled` flag once, while they boot.
 */
class ObservabilitySettings extends AdminSettings
{
    // Both record out of the box; an admin can switch either off.
    #[ConfigKey('chatvia.pulse.recording')]
    public ?bool $pulse_enabled = null;

    #[ConfigKey('chatvia.spectra.recording')]
    public ?bool $spectra_enabled = null;

    #[ConfigKey('chatvia.spectra.retention_days')]
    public ?int $spectra_retention_days = null;

    // Off keeps only each call's counts, costs and times.
    #[ConfigKey('spectra.storage.store_prompts')]
    public ?bool $spectra_store_prompts = null;

    #[ConfigKey('spectra.storage.store_system_prompts')]
    public ?bool $spectra_store_system_prompts = null;

    #[ConfigKey('spectra.storage.store_responses')]
    public ?bool $spectra_store_responses = null;

    #[ConfigKey('spectra.storage.store_tools')]
    public ?bool $spectra_store_tools = null;

    // Unset follows spectra_store_prompts: the raw request holds the prompts.
    #[ConfigKey('spectra.storage.store_request_data')]
    public ?bool $spectra_store_request_data = null;

    #[ConfigKey('spectra.storage.store_embeddings')]
    public ?bool $spectra_store_embeddings = null;

    #[ConfigKey('spectra.tracking.trace_per_request')]
    public ?bool $spectra_trace_per_request = null;

    #[ConfigKey('spectra.tracking.use_traceparent')]
    public ?bool $spectra_use_traceparent = null;

    #[ConfigKey('spectra.tracking.capture_request_id')]
    public ?bool $spectra_capture_request_id = null;

    #[ConfigKey('chatvia.issues.enabled')]
    public ?bool $issues_enabled = null;

    #[ConfigKey('chatvia.issues.retention_days')]
    public ?int $issues_retention_days = null;

    public static function group(): string
    {
        return 'observability';
    }
}
