<?php

namespace Domain\Integration\Mcp\Services\Tools\Analytics;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Services\AnalyticsPeriodResolver;
use Domain\Analytics\Services\Period;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Connection;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Tool;
use Support\Enums\User\UserType;

/**
 * Shared base for the internal Account-Analytics tools. Each tool answers a
 * cluster of "numbers about my account" questions for the workspace OWNER.
 *
 * Identity: the owner-facing analytics chat stamps `X-Chatvia-Workspace-Id`
 * (the workspace external_id) on every tools/call; we resolve the workspace
 * from it here. These tools are NEVER attached to an agent and never see a
 * visitor — they are internal-only (not listed in config/connectors.php).
 *
 * Most metrics read the durable analytics rollups (the same tables the
 * Analytics page uses) filtered by `workspace_id`, which carry counts that
 * survive deletion of the raw chat rows. A few slices that have no rollup
 * (channel, leads, handoff, device) read the live main-DB tables scoped to
 * the workspace's agent ids.
 */
abstract class AnalyticsTool extends Tool
{
    /** Resolve the calling workspace from the signed identity header. */
    protected function workspace(): ?User
    {
        $externalId = (string) app(HttpRequest::class)->header('X-Chatvia-Workspace-Id', '');

        if ($externalId === '') {
            return null;
        }

        return User::query()
            ->where('external_id', $externalId)
            ->whereIn('type', UserType::workspaceTypes())
            ->first();
    }

    /** The analytics DB connection (may be a separate Postgres database). */
    protected function analytics(): Connection
    {
        return DB::connection(config('chatvia.analytics.connection'));
    }

    protected function resolvePeriod(User $workspace, array $validated): Period
    {
        return app(AnalyticsPeriodResolver::class)->resolve(
            $workspace,
            $validated['period'] ?? null,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
        );
    }

    /**
     * Resolve the `agent` argument to a scope. Returns the matching agent (or
     * null for "whole account") plus the list of agent ids to filter main-DB
     * tables on. An unknown agent id yields an empty scope (id -1) so the tool
     * returns zeros rather than the whole account.
     *
     * @return array{agent: ?Agent, ids: list<int>}
     */
    protected function agentScope(User $workspace, array $validated): array
    {
        if (! empty($validated['agent'])) {
            $agent = $workspace->agents()->where('external_id', $validated['agent'])->first();

            return ['agent' => $agent, 'ids' => [$agent?->id ?? -1]];
        }

        return ['agent' => null, 'ids' => $workspace->agents()->pluck('id')->all()];
    }

    /** Shared validation rules every analytics tool merges into validate(). */
    protected function commonRules(): array
    {
        return [
            'period' => ['nullable', 'string', 'in:current_cycle,previous_cycle,today,last_7_days,last_30_days,this_month,last_month,all_time'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'agent' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** Shared schema fragment (period + optional explicit dates + agent). */
    protected function commonSchema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()
                ->enum(['current_cycle', 'previous_cycle', 'today', 'last_7_days', 'last_30_days', 'this_month', 'last_month', 'all_time'])
                ->description('Time window. Defaults to "current_cycle" — the current billing cycle. Use "previous_cycle" for the one before, or a rolling window like "last_7_days"/"last_30_days".')
                ->default('current_cycle'),
            'start_date' => $schema->string()
                ->description('Optional explicit start date (YYYY-MM-DD). When given together with end_date it overrides `period`.'),
            'end_date' => $schema->string()
                ->description('Optional explicit end date (YYYY-MM-DD).'),
            'agent' => $schema->string()
                ->description('Optional agent id to narrow the numbers to a single agent. Omit for the whole account (all agents).'),
        ];
    }

    /** Round a numeric/null value to a friendly precision, or null. */
    protected function round(int|float|null $value, int $precision = 1): int|float|null
    {
        return $value === null ? null : round((float) $value, $precision);
    }

    /** Percent-of-whole, null-safe. */
    protected function pct(int|float $part, int|float $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
    }
}
