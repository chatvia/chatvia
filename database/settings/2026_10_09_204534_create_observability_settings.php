<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\ObservabilitySettings, all empty: an empty
 * value keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('observability', function (SettingsBlueprint $group): void {
            $group->add('pulse_enabled');
            $group->add('spectra_enabled');
            $group->add('spectra_retention_days');
            $group->add('spectra_store_prompts');
            $group->add('spectra_store_system_prompts');
            $group->add('spectra_store_responses');
            $group->add('spectra_store_tools');
            $group->add('spectra_store_request_data');
            $group->add('spectra_store_embeddings');
            $group->add('spectra_trace_per_request');
            $group->add('spectra_use_traceparent');
            $group->add('spectra_capture_request_id');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'observability')->delete();
    }
};
