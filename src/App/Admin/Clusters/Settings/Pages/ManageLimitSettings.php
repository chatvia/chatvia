<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\LimitSettings;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Advanced limits: how long the logs are kept, the per-tool rate limit and
 * the guardrails master switch.
 */
class ManageLimitSettings extends AdminSettingsPage
{
    #[\Override]
    protected static string $settings = LimitSettings::class;

    #[\Override]
    protected static ?string $title = 'Retention & limits';

    #[\Override]
    protected static ?string $slug = 'limits';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    #[\Override]
    protected static ?int $navigationSort = 11;

    public static function description(): string
    {
        return 'Log retention, tool rate limits and guardrails.';
    }

    public static function overviewGroup(): string
    {
        return 'Brand and platform';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('tool_calls', 'Tool calls', 'Every tool call of an agent is logged and rate limited.', Heroicon::OutlinedWrench, fn (): array => [
                $this->integer('tool_rate_limit', 'Calls per tool',
                    help: 'How many times an agent may call one tool in the time window.', min: 1),
                $this->integer('tool_rate_window_seconds', 'Time window (seconds)', min: 1),
                $this->integer('tool_audit_retention_days', 'Days to keep the tool-call log', min: 1),
            ])->summary(fn (): string => sprintf('%s calls per %s seconds · log kept %s days',
                $this->valueInUse('tool_rate_limit'), $this->valueInUse('tool_rate_window_seconds'), $this->valueInUse('tool_audit_retention_days'))),
            SettingsCard::make('guardrails', 'Guardrails', 'The safety checks on every chat: personal data, prompt injection, off-topic and toxicity.', Heroicon::OutlinedShieldCheck, fn (): array => [
                $this->toggle('guardrails_enabled', 'Guardrails',
                    help: 'When off, no check runs on any agent, whatever the agent\'s own settings.'),
                $this->integer('guardrail_retention_days', 'Days to keep the guardrail log', min: 1),
            ])->summary(fn (): string => ($this->valueInUse('guardrails_enabled') ? 'On' : 'Off').' · log kept '.$this->valueInUse('guardrail_retention_days').' days'),
            SettingsCard::make('security_log', 'Security log', 'Sign-ins, membership, configuration and data changes of each workspace.', Heroicon::OutlinedClipboardDocumentCheck, fn (): array => [
                $this->integer('security_audit_retention_days', 'Days to keep the security log',
                    help: 'A workspace may choose fewer days.', min: 1),
                $this->integer('security_audit_retention_floor_days', 'Fewest days a workspace may choose', min: 1),
            ])->summary(fn (): string => 'Kept '.$this->valueInUse('security_audit_retention_days').' days'),
            SettingsCard::make('ai_call_log', 'AI call log', 'Every AI call with its request, reply, tokens and cost, for the staff AI usage tools.', Heroicon::OutlinedCpuChip, fn (): array => [
                $this->integer('spectra_retention_days', 'Days to keep the AI call log',
                    help: 'Older calls are deleted once a day.', min: 1),
            ])->summary(fn (): string => 'Kept '.$this->valueInUse('spectra_retention_days').' days'),
        ];
    }
}
