<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Platform;

use Domain\Analytics\Data\Platform\CountTrendData;
use Domain\Analytics\Data\Platform\PlatformOverviewData;
use Domain\Analytics\Data\Platform\PlatformScopeData;
use Domain\Analytics\Services\Period;
use Domain\Form\Enums\FormSubmissionStatus;
use Illuminate\Support\Facades\DB;

/**
 * The headline numbers of the admin panel's dashboard: messages,
 * conversations, new visitors and reply time, with trends against the
 * previous equally long window, plus workspace sign-ups, agents and leads.
 */
class PlatformOverviewQuery extends PlatformAnalyticsQuery
{
    public function get(PlatformScopeData $scope): PlatformOverviewData
    {
        $period = $scope->period;
        $current = $this->traffic($scope, $period);
        $previous = $this->traffic($scope, $period->previousWindow());

        return new PlatformOverviewData(
            messages: new CountTrendData((int) $current->messages, (int) $previous->messages),
            conversations: new CountTrendData((int) $current->conversations, (int) $previous->conversations),
            newVisitors: $this->newVisitors($scope),
            avgReplyMs: (int) $current->response_ms_count > 0
                ? (int) round((int) $current->response_ms_sum / (int) $current->response_ms_count)
                : null,
            newWorkspaces: new CountTrendData(
                $this->workspacesCreatedIn($scope, $period),
                $this->workspacesCreatedIn($scope, $period->previousWindow()),
            ),
            totalWorkspaces: $scope->isWholePlatform()
                ? $this->workspaceAccounts()->where('created_at', '<=', $period->end)->count()
                : 0,
            activeWorkspaces: $scope->isWholePlatform() ? (int) $current->active_workspaces : 0,
            newAgents: $this->agents($scope)->whereBetween('created_at', [$period->start, $period->end])->count(),
            totalAgents: $this->agents($scope)->where('created_at', '<=', $period->end)->count(),
            leads: $this->leads($scope),
        );
    }

    /**
     * Message and conversation totals, reply time and active workspaces over
     * one window.
     */
    private function traffic(PlatformScopeData $scope, Period $period): object
    {
        return $this->rollups($scope, $period)
            ->selectRaw('COALESCE(SUM(user_messages + assistant_messages), 0) messages')
            ->selectRaw('COALESCE(SUM(conversations_started), 0) conversations')
            ->selectRaw('COALESCE(SUM(response_ms_sum), 0) response_ms_sum')
            ->selectRaw('COALESCE(SUM(response_ms_count), 0) response_ms_count')
            ->selectRaw('COUNT(DISTINCT workspace_id) FILTER (WHERE user_messages + assistant_messages > 0) active_workspaces')
            ->first();
    }

    private function workspacesCreatedIn(PlatformScopeData $scope, Period $period): int
    {
        return $scope->isWholePlatform()
            ? $this->workspaceAccounts()->whereBetween('created_at', [$period->start, $period->end])->count()
            : 0;
    }

    /** Visitor sessions first seen in the window. */
    private function newVisitors(PlatformScopeData $scope): int
    {
        return $this->analytics()->table('analytics_session_firsts')
            ->when(! $scope->isWholePlatform(), fn ($query) => $query->where('workspace_id', $scope->workspaceId))
            ->whereBetween('first_seen_at', [$scope->period->start, $scope->period->end])
            ->count();
    }

    /**
     * Forms submitted in chats during the window, by the time they were
     * submitted. Both scopes match the partial index on (workspace_id,
     * submitted_at) of submitted rows.
     */
    private function leads(PlatformScopeData $scope): int
    {
        return DB::table('form_submissions')
            ->when(! $scope->isWholePlatform(), fn ($query) => $query->where('workspace_id', $scope->workspaceId))
            ->where('status', FormSubmissionStatus::Submitted->value)
            ->whereBetween('submitted_at', [$scope->period->start, $scope->period->end])
            ->count();
    }
}
