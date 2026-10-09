<?php

namespace Domain\Analytics\Queries\Account;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\FeedbackTallyData;
use Domain\Analytics\Enums\AnalyticsEventType;
use Domain\Analytics\Services\AnalyticsDatabase;
use Domain\Analytics\Services\Period;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Shared building blocks for the account-analytics read models: each one
 * answers one cluster of "numbers about my account" questions for a
 * workspace owner, scoped by an {@see AnalyticsScopeData}.
 *
 * Most metrics read the durable analytics tables (the same ones the Analytics
 * page uses) by `workspace_id`, so the counts survive deletion of the raw chat
 * rows. A few slices with no rollup (channel, leads, handoff, device) read the
 * live main-DB tables, scoped to the workspace's agent ids.
 *
 * Counts that must respect a window's exact start (a billing cycle can start
 * mid-hour) read the granular event log or per-conversation metrics, not the
 * hour-bucketed rollup, whose first partial hour would fall before the start.
 */
abstract class AccountAnalyticsQuery
{
    /** The analytics DB connection (may be a separate Postgres database). */
    protected function analytics(): Connection
    {
        return app(AnalyticsDatabase::class)->connection();
    }

    /**
     * Events of one type in the window, by exact `occurred_at`.
     */
    protected function events(AnalyticsScopeData $scope, AnalyticsEventType $eventType, ?Period $period = null): Builder
    {
        $period ??= $scope->period;

        return $this->forScopedAgent($scope, $this->analytics()->table('analytics_events')
            ->where('workspace_id', $scope->workspace->id)
            ->where('event_type', $eventType->value)
            ->whereBetween('occurred_at', [$period->start, $period->end]));
    }

    /**
     * Messages sent in the window (visitor messages and AI replies).
     */
    protected function messageEvents(AnalyticsScopeData $scope, ?Period $period = null): Builder
    {
        return $this->events($scope, AnalyticsEventType::MessageSent, $period);
    }

    /**
     * The per-agent hourly rollup, from the hour the window starts in.
     */
    protected function hourlyRollups(AnalyticsScopeData $scope): Builder
    {
        return $this->forScopedAgent($scope, $this->analytics()->table('analytics_agent_hourly_rollups')
            ->where('workspace_id', $scope->workspace->id)
            ->whereBetween('bucket_hour', [$scope->period->start->copy()->startOfHour(), $scope->period->end]));
    }

    /**
     * A per-day rollup table, by calendar day so a window starting mid-day
     * keeps its first partial day.
     */
    protected function dailyRollup(AnalyticsScopeData $scope, string $table): Builder
    {
        return $this->forScopedAgent($scope, $this->analytics()->table($table)
            ->where('workspace_id', $scope->workspace->id)
            ->whereRaw('bucket_day BETWEEN ?::date AND ?::date', [$scope->period->start->toDateString(), $scope->period->end->toDateString()]));
    }

    /**
     * Per-conversation metrics for conversations started in the window.
     */
    protected function conversationMetrics(AnalyticsScopeData $scope): Builder
    {
        return $this->forScopedAgent($scope, $this->analytics()->table('analytics_conversation_metrics')
            ->where('workspace_id', $scope->workspace->id)
            ->whereBetween('started_at', [$scope->period->start, $scope->period->end]));
    }

    /**
     * Visitor sessions first seen in the window (new visitors).
     */
    protected function sessionFirsts(AnalyticsScopeData $scope): Builder
    {
        return $this->forScopedAgent($scope, $this->analytics()->table('analytics_session_firsts')
            ->where('workspace_id', $scope->workspace->id)
            ->whereBetween('first_seen_at', [$scope->period->start, $scope->period->end]));
    }

    /**
     * Live (not deleted) conversations of the scoped agents, aliased `c`,
     * joined to their session `s`. Not bound to the window.
     */
    protected function liveConversations(AnalyticsScopeData $scope): Builder
    {
        return DB::table('chat_conversations as c')
            ->join('chat_sessions as s', 'c.chat_session_id', '=', 's.id')
            ->whereIn('s.agent_id', $scope->agentIds)
            ->whereNull('c.deleted_at');
    }

    /** Leads — submitted forms — captured in the window. */
    protected function leadCount(AnalyticsScopeData $scope): int
    {
        return DB::table('form_submissions')
            ->whereIn('agent_id', $scope->agentIds)
            ->where('status', 'submitted')
            ->whereBetween('created_at', [$scope->period->start, $scope->period->end])
            ->count();
    }

    /** Thumbs up / down given in the window. */
    protected function feedback(AnalyticsScopeData $scope): FeedbackTallyData
    {
        $row = $this->events($scope, AnalyticsEventType::FeedbackGiven)
            ->selectRaw("COUNT(*) FILTER (WHERE feedback = 'positive') positive, COUNT(*) FILTER (WHERE feedback = 'negative') negative")
            ->first();

        return new FeedbackTallyData((int) $row->positive, (int) $row->negative);
    }

    /** Round a numeric/null value to a friendly precision, or null. */
    protected function roundOrNull(int|float|string|null $value, int $precision = 1): int|float|null
    {
        return $value === null ? null : round((float) $value, $precision);
    }

    /** Percent-of-whole, null-safe. */
    protected function pct(int|float $part, int|float $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
    }

    /** Narrow an analytics-table query to the scoped agent, when there is one. */
    private function forScopedAgent(AnalyticsScopeData $scope, Builder $query): Builder
    {
        return $query->when($scope->agentId !== null, fn (Builder $query): Builder => $query->where('agent_id', $scope->agentId));
    }
}
