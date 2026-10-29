<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Resources\Issues\IssueResource;
use App\Admin\Settings\ObservabilitySettings;
use App\Admin\Settings\ToolRecording;
use App\Admin\StaffTool;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Icons\Heroicon;
use Laravel\Pulse\Support\CacheStoreResolver;

/**
 * The staff tools that watch the install. Pulse, the app's health: whether
 * it records. Spectra, the log of every AI call: whether it records, how
 * long it keeps calls, what it saves of each one besides the counts, costs
 * and times, and (in its Advanced part) how it groups calls into traces. Radar, the issues an
 * admin can fix (a refused API key, no credit…): whether they are kept, and
 * for how long.
 */
class ManageObservabilitySettings extends AdminSettingsPage
{
    /** The .env switch that leaves a tool out of the install, by its recording setting. */
    private const array TOOL_SWITCHES = [
        'pulse_enabled' => 'PULSE_ENABLED',
        'spectra_enabled' => 'SPECTRA_ENABLED',
    ];

    /** What Spectra can save of a call, besides the counts, costs and times it always keeps. */
    private const array SAVED_PARTS = [
        'spectra_store_prompts',
        'spectra_store_system_prompts',
        'spectra_store_responses',
        'spectra_store_tools',
        'spectra_store_request_data',
        'spectra_store_embeddings',
    ];

    /** How Spectra groups calls into traces. */
    private const array TRACE_PARTS = [
        'spectra_trace_per_request',
        'spectra_use_traceparent',
        'spectra_capture_request_id',
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
        return 'Pulse, the app\'s health, Spectra, the log of every AI call, and Radar, the issues you can fix.';
    }

    /**
     * `pulse:check` (Pulse's server stats) runs for hours and never sees a
     * save, so a change of the Pulse switch restarts it: the process that
     * keeps it running starts it again with the saved choice. The signal is
     * the cache key `pulse:restart` writes; Pulse registers that command in
     * the console only, so a web request cannot call it.
     */
    #[\Override]
    public function save(): void
    {
        $pulse = $this->valueInUse('pulse_enabled');

        parent::save();

        if ($this->valueInUse('pulse_enabled') !== $pulse) {
            app(CacheStoreResolver::class)->store()->forever('laravel:pulse:restart', now()->getTimestamp());
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
            1 => ["{$recording[0]} recording", 'success'],
            default => ['Both recording', 'success'],
        };
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('pulse', 'Pulse', 'The app\'s health: slow pages, queries and jobs, errors, queues and the servers.', Heroicon::OutlinedHeart, fn (): array => [
                $this->recordingToggle('pulse_enabled', 'Record app health', help: 'Records slow pages, errors, queues and server load to show in Pulse. Data older than 7 days is deleted.'),
            ])->summary(fn (): string => $this->recordingSummary('pulse_enabled'))
                ->console($this->toolUrl(StaffTool::Pulse), 'Open Pulse'),
            SettingsCard::make('spectra', 'Spectra', 'A log of every AI call: the model, the tokens, the cost and the time it took.', Heroicon::OutlinedCpuChip, fn (): array => [
                $this->recordingToggle('spectra_enabled', 'Record AI calls'),
                $this->integer('spectra_retention_days', 'Keep AI calls for (days)', help: 'AI calls older than this are deleted once a day.', min: 1, max: 3650),
                Fieldset::make('What Spectra saves, besides the counts, costs and times')
                    ->columns(1)
                    ->schema([
                        $this->toggle('spectra_store_prompts', 'Prompts', help: 'Saves the messages sent to the AI, which include what people write in chats.'),
                        $this->toggle('spectra_store_system_prompts', 'System prompts', help: 'Saves each agent\'s instructions, along with the parts of your knowledge found for the question.'),
                        $this->toggle('spectra_store_responses', 'Replies', help: 'Saves what the AI answered.'),
                        $this->toggle('spectra_store_tools', 'Tools', help: 'Saves the list of tools the AI could use. It can be long.'),
                        $this->toggle('spectra_store_request_data', 'Request data', help: 'Saves the whole request sent to the AI service. That repeats the prompts and adds settings such as the temperature.'),
                        $this->toggle('spectra_store_embeddings', 'Embeddings', help: 'Saves the numbers that search uses to compare meaning. They are large and rarely useful, so this is off by default.'),
                    ]),
            ])->properties('spectra_enabled', 'spectra_retention_days', ...self::SAVED_PARTS, ...self::TRACE_PARTS)
                ->summary(fn (): string => $this->recordingSummary('spectra_enabled'))
                ->console($this->toolUrl(StaffTool::Spectra), 'Open Spectra')
                ->advancedFields(fn (): array => [
                    $this->toggle('spectra_trace_per_request', 'One trace per request', help: 'Groups the AI calls of one page visit or background job, so they show as one chain. Off shows each call on its own.'),
                    $this->toggle('spectra_use_traceparent', 'Join incoming traces', help: 'When another monitoring tool sends a trace ID with a request, the AI calls of that request join its trace.'),
                    $this->toggle('spectra_capture_request_id', 'Keep the request ID', help: 'Saves the ID of the page request with each AI call, so you can find the same request in the server logs.'),
                ]),
            SettingsCard::make('issues', 'Radar', 'Issues you can fix, such as a refused API key or an account out of credit.', Heroicon::OutlinedSignal, fn (): array => [
                $this->toggle('issues_enabled', 'Keep issues', help: 'Saves each issue with an AI provider or another service you set up (a refused key, no credit, a removed model, a service that is down), with who it happened to, to show on Radar.'),
                $this->integer('issues_retention_days', 'Keep issues for (days)', help: 'Issues not seen for this many days are deleted once a day.', min: 1, max: 3650),
            ])->summary(fn (): string => $this->issuesSummary())
                ->console(IssueResource::getUrl(), 'Open Radar'),
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
            return $this->toggle($property, $label, help: 'The server\'s .env file has '.self::TOOL_SWITCHES[$property].'=false, which leaves this tool out, so it cannot record.')
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

    private function issuesSummary(): string
    {
        return $this->valueInUse('issues_enabled') === true ? 'On' : 'Off';
    }
}
