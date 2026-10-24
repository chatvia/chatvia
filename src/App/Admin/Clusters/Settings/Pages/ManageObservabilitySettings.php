<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\ObservabilitySettings;
use App\Admin\Settings\ToolRecording;
use App\Admin\StaffTool;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * The staff tools that watch the install. Pulse, the app's health: whether
 * it records. Spectra, the log of every AI call: whether it records, how
 * long it keeps calls, what it saves of each one besides the counts, costs
 * and times, and how it groups calls into traces.
 */
class ManageObservabilitySettings extends AdminSettingsPage
{
    /** The .env switch that leaves a tool out of the install, by its recording setting. */
    private const array TOOL_SWITCHES = [
        'pulse_enabled' => 'PULSE_ENABLED',
        'spectra_enabled' => 'SPECTRA_ENABLED',
    ];

    /** What Spectra can save of a call, by property, as the card's summary names it. */
    private const array SAVED_PARTS = [
        'spectra_store_prompts' => 'prompts',
        'spectra_store_system_prompts' => 'system prompts',
        'spectra_store_responses' => 'replies',
        'spectra_store_tools' => 'tools',
        'spectra_store_request_data' => 'request data',
        'spectra_store_embeddings' => 'embeddings',
    ];

    #[\Override]
    protected static string $settings = ObservabilitySettings::class;

    #[\Override]
    protected static ?string $title = 'Observability';

    #[\Override]
    protected static ?string $slug = 'observability';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    #[\Override]
    protected static ?int $navigationSort = 16;

    public static function description(): string
    {
        return 'Pulse, the app\'s health, and Spectra, the log of every AI call.';
    }

    /**
     * `pulse:check` (Pulse's server stats) runs for hours and never sees a
     * save, so a change of the Pulse switch restarts it: the process that
     * keeps it running starts it again with the saved choice.
     */
    #[\Override]
    public function save(): void
    {
        $pulse = $this->valueInUse('pulse_enabled');

        parent::save();

        if ($this->valueInUse('pulse_enabled') !== $pulse) {
            Artisan::call('pulse:restart');
        }
    }

    public static function overviewGroup(): string
    {
        return 'Platform and brand';
    }

    public function overviewStatus(): array
    {
        $recording = array_keys(array_filter(['Pulse' => 'pulse_enabled', 'Spectra' => 'spectra_enabled'], $this->recording(...)));

        return match (count($recording)) {
            0 => ['Not recording', 'gray'],
            1 => [$recording[0].' recording', 'success'],
            default => ['Both recording', 'success'],
        };
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('pulse', 'Pulse', 'The app\'s health: slow pages, queries and jobs, errors, queues and the servers.', Heroicon::OutlinedHeart, fn (): array => [
                $this->recordingToggle('pulse_enabled', 'Record app health', help: 'Data older than 7 days is removed.'),
            ])->summary(fn (): string => $this->recordingSummary('pulse_enabled'))
                ->console($this->toolUrl(StaffTool::Pulse), 'Open Pulse'),
            SettingsCard::make('spectra', 'Spectra', 'A log of every AI call: the model, the tokens, the cost and the time it took.', Heroicon::OutlinedCpuChip, fn (): array => [
                $this->recordingToggle('spectra_enabled', 'Record AI calls'),
                $this->integer('spectra_retention_days', 'Keep AI calls for (days)', help: 'Older calls are removed every night.', min: 1, max: 3650),
            ])->summary(fn (): string => $this->recordingSummary('spectra_enabled'))
                ->console($this->toolUrl(StaffTool::Spectra), 'Open Spectra'),
            SettingsCard::make('saved', 'What Spectra saves', 'Besides the counts, costs and times, which Spectra always keeps.', Heroicon::OutlinedDocumentText, fn (): array => [
                $this->toggle('spectra_store_prompts', 'Prompts', help: 'The messages sent to the AI, which include what visitors and chatters write.'),
                $this->toggle('spectra_store_system_prompts', 'System prompts', help: 'The instructions of each agent, with the parts of your knowledge found for the question.'),
                $this->toggle('spectra_store_responses', 'Replies', help: 'What the AI answered.'),
                $this->toggle('spectra_store_tools', 'Tools', help: 'The tools the AI was offered. They can be long.'),
                $this->toggle('spectra_store_request_data', 'Request data', help: 'The whole request sent to the AI service: the prompts again, plus settings such as the temperature.'),
                $this->toggle('spectra_store_embeddings', 'Embeddings', help: 'The numbers of each embedding. They are large and rarely useful, so they are left out by default.'),
            ])->summary(fn (): string => $this->savedSummary()),
            SettingsCard::make('traces', 'Traces', 'How Spectra groups AI calls, so the calls of one reply show as one chain.', Heroicon::OutlinedLink, fn (): array => [
                $this->toggle('spectra_trace_per_request', 'One trace per request', help: 'Every AI call of one page request, job or command shares a trace, and so do the jobs it starts. Off gives each call a trace of its own.'),
                $this->toggle('spectra_use_traceparent', 'Join incoming traces', help: 'When a request comes with a W3C "traceparent" header, as from an OpenTelemetry proxy, its AI calls join that trace.'),
                $this->toggle('spectra_capture_request_id', 'Keep the request ID', help: 'Saves the X-Request-ID header of the page request with each call, to find it in the server logs.'),
            ])->advanced(),
        ];
    }

    /** Whether .env (TOOL_SWITCHES) leaves the tool of a recording setting in this install. */
    private function installed(string $property): bool
    {
        $tools = app(ToolRecording::class);

        return $property === 'pulse_enabled' ? $tools->pulseInstalled() : $tools->spectraInstalled();
    }

    /** Whether a tool records: installed by .env and switched on here. */
    private function recording(string $property): bool
    {
        return $this->installed($property) && $this->valueInUse($property) === true;
    }

    /** A tool's recording switch, locked while .env leaves the tool out. */
    private function recordingToggle(string $property, string $label, ?string $help = null): Toggle
    {
        if (! $this->installed($property)) {
            return $this->toggle($property, $label, help: self::TOOL_SWITCHES[$property].'=false in .env leaves this tool out of the install, so it cannot record.')
                ->disabled();
        }

        return $this->toggle($property, $label, $help);
    }

    private function recordingSummary(string $property): string
    {
        return match (true) {
            ! $this->installed($property) => 'Off in .env',
            $this->recording($property) => 'Recording',
            default => 'Off',
        };
    }

    /** Where a card's link opens the tool; none when the admin cannot open it. */
    private function toolUrl(StaffTool $tool): ?string
    {
        return $tool->unavailableReason() === null ? $tool->url() : null;
    }

    private function savedSummary(): string
    {
        $saved = array_keys(array_filter(self::SAVED_PARTS, fn (string $part, string $property): bool => (bool) $this->valueInUse($property), ARRAY_FILTER_USE_BOTH));

        if ($saved === []) {
            return 'Counts, costs and times only';
        }

        return Str::ucfirst(collect($saved)->map(fn (string $property): string => self::SAVED_PARTS[$property])->join(', ', ' and '));
    }
}
