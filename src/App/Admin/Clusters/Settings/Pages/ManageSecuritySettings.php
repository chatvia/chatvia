<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\SecuritySettings;
use BackedEnum;
use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * The install's protections and their logs: the bot check (Cloudflare
 * Turnstile) of the sign-up, sign-in and password reset forms, the
 * guardrails, the tool-call log and rate limit, the security audit log, and
 * the security headers. Each log's retention sits on the card of what it
 * records.
 */
class ManageSecuritySettings extends AdminSettingsPage
{
    private const array CSP_MODES = [
        'report-only' => 'Report only (browsers report, nothing is blocked)',
        'enforce' => 'Enforce',
        'off' => 'Off (no security headers)',
    ];

    /** The form switches (App\Http\Auth\BotCheckForm), by what they cover. */
    private const array TURNSTILE_FORMS = [
        'turnstile_sign_up' => 'sign-up',
        'turnstile_sign_in' => 'sign-in',
        'turnstile_password_reset' => 'password reset',
    ];

    #[\Override]
    protected static string $settings = SecuritySettings::class;

    #[\Override]
    protected static ?string $title = 'Security';

    #[\Override]
    protected static ?string $slug = 'security';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    #[\Override]
    protected static ?int $navigationSort = 14;

    public static function description(): string
    {
        return 'Turnstile (bot check), guardrails, tool-call limits, the audit log and security headers.';
    }

    public static function overviewGroup(): string
    {
        return 'Access and security';
    }

    /**
     * Whether the bot check is on: a half set-up one first.
     *
     * @return array{0: string, 1: string}
     */
    #[\Override]
    public function overviewStatus(): array
    {
        return $this->unfinishedStatus()
            ?? (filled($this->valueInUse('turnstile_site_key')) && filled($this->valueInUse('turnstile_secret_key'))
                ? ['Turnstile on', 'success']
                : ['Turnstile off', 'gray']);
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('turnstile', 'Turnstile (Bot check)', 'Cloudflare Turnstile stops bots on the public forms, mostly without a puzzle for people.', Heroicon::OutlinedShieldCheck, fn (): array => [
                $this->text('turnstile_site_key', 'Site key', required: true),
                $this->secret('turnstile_secret_key', 'Secret key'),
                $this->toggle('turnstile_sign_up', 'Check sign-up', help: 'Shows the bot check when someone creates a workspace account.'),
                $this->toggle('turnstile_sign_in', 'Check sign-in', help: 'Shows the bot check when someone signs in with a password or asks for a sign-in link.'),
                $this->toggle('turnstile_password_reset', 'Check password reset', help: 'Shows the bot check when someone asks for a link to reset their password.'),
            ])->required('turnstile_site_key', 'turnstile_secret_key')
                ->console('https://dash.cloudflare.com/?to=/:account/turnstile')
                ->summary(fn (): string => $this->turnstileSummary()),
            SettingsCard::make('guardrails', 'Guardrails', 'The safety checks on every chat: personal data, prompt injection, off-topic and toxicity.', Heroicon::OutlinedShieldExclamation, fn (): array => [
                $this->toggle('guardrails_enabled', 'Guardrails',
                    help: 'Runs the safety checks on every chat. Off skips them for all agents, even agents set to use them.'),
                $this->integer('guardrail_retention_days', 'Days to keep the guardrail log', min: 1),
                $this->integer('max_message_chars', 'Longest visitor message (characters)',
                    help: 'Longer messages in the chat widget or the hosted chat are turned away before the AI reads them.', min: 200, max: 32000),
            ])->summary(fn (): string => ($this->valueInUse('guardrails_enabled') ? 'On' : 'Off').' · log kept '.$this->valueInUse('guardrail_retention_days').' days · messages up to '.$this->valueInUse('max_message_chars').' characters'),
            SettingsCard::make('tool_calls', 'Tool calls', 'How far an agent may go with tools, and the log of every tool call.', Heroicon::OutlinedWrenchScrewdriver, fn (): array => [
                $this->integer('tool_max_rounds', 'Tool rounds per answer',
                    help: 'How many times an agent may use its tools for one answer. After that, it answers with what it has found.', min: 1, max: 20),
                $this->toggle('tool_approval_enabled', 'Ask before tools that make changes',
                    help: 'Tools set to need approval wait for a person to say yes. Off lets every tool run without asking.'),
                $this->integer('tool_audit_retention_days', 'Days to keep the tool-call log', min: 1),
            ])->advancedFields(fn (): array => [
                $this->integer('tool_rate_limit', 'Calls per tool',
                    help: 'The most times one agent may use the same tool within the time window. Calls over the limit are refused.', min: 1),
                $this->integer('tool_rate_window_seconds', 'Time window (seconds)', help: 'How long tool calls are counted for. The count starts again after this.', min: 1),
                $this->integer('tool_result_max_chars', 'Longest tool result (characters)',
                    help: 'Longer results are cut short before the agent reads them, so one huge result cannot crowd out the rest of the chat.', min: 1000),
                $this->integer('tool_approval_ttl_minutes', 'Minutes an approval stays open',
                    help: 'A request for approval that nobody answers in this time expires, and the tool does not run.', min: 1),
            ])->summary(fn (): string => sprintf('Up to %s rounds · approval %s · %s calls per %s seconds · log kept %s days',
                $this->valueInUse('tool_max_rounds'), $this->valueInUse('tool_approval_enabled') ? 'on' : 'off',
                $this->valueInUse('tool_rate_limit'), $this->valueInUse('tool_rate_window_seconds'), $this->valueInUse('tool_audit_retention_days'))),
            SettingsCard::make('audit', 'Security audit log', 'A record of sign-ins, membership, configuration and data changes in every workspace.', Heroicon::OutlinedClipboardDocumentList, fn (): array => [
                $this->toggle('security_audit_enabled', 'Keep the security audit log',
                    help: 'Records sign-ins and changes to members, settings and data in every workspace. Each workspace sees only its own log.'),
                $this->integer('security_audit_retention_days', 'Days to keep the security log',
                    help: 'Older entries are deleted. A workspace may choose to keep them for fewer days.', min: 1),
                $this->integer('security_audit_retention_floor_days', 'Fewest days a workspace may choose', min: 1)
                    // A floor above the days would leave a workspace no value to choose.
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $days = SecuritySettings::cast('security_audit_retention_days', $get('security_audit_retention_days')) ?? $this->defaultValue('security_audit_retention_days');

                        if (is_numeric($value) && is_numeric($days) && (int) $value > (int) $days) {
                            $fail('The fewest days cannot be more than the days to keep the security log.');
                        }
                    }),
            ])->summary(fn (): string => ($this->valueInUse('security_audit_enabled') ? 'On' : 'Off').' · kept '.$this->valueInUse('security_audit_retention_days').' days'),
            SettingsCard::make('headers', 'Security headers', 'The Content Security Policy of every page.', Heroicon::OutlinedCodeBracketSquare, fn (): array => [
                $this->choice('csp_mode', 'Content Security Policy', self::CSP_MODES,
                    help: 'Browser rules that block risky content and stop other sites from showing your dashboard and sign-in pages inside theirs. Enforce turns them fully on.'),
                $this->url('csp_report_uri', 'Report address', help: 'Optional. Browsers send a report here each time the rules block, or would block, something.'),
            ])->summary(fn (): string => self::CSP_MODES[(string) $this->valueInUse('csp_mode')] ?? $this->shown($this->valueInUse('csp_mode')))
                ->advanced(),
        ];
    }

    private function turnstileSummary(): string
    {
        $on = array_filter(self::TURNSTILE_FORMS, fn (string $property): bool => (bool) $this->valueInUse($property), ARRAY_FILTER_USE_KEY);

        return $on === [] ? 'On no form' : 'Checks '.implode(', ', $on);
    }
}
