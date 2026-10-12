<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Platform;

use Domain\Analytics\Data\Platform\PlatformScopeData;
use Domain\Analytics\Data\Platform\TopAgentData;

/**
 * The agents that sent and received the most messages in the window, busiest
 * first, across the install or within the scoped workspace.
 */
class TopAgentsQuery extends PlatformAnalyticsQuery
{
    /**
     * @return list<TopAgentData>
     */
    public function get(PlatformScopeData $scope, int $limit = 10): array
    {
        $rows = $this->rollups($scope)
            ->groupBy('agent_id', 'workspace_id')
            ->selectRaw('agent_id, workspace_id, SUM(user_messages + assistant_messages) messages, SUM(conversations_started) conversations')
            ->havingRaw('SUM(user_messages + assistant_messages) > 0')
            ->orderByDesc('messages')
            ->orderBy('agent_id')
            ->limit($limit)
            ->get();

        // Names live on the main DB; resolve them in PHP so this works even
        // when analytics runs on a separate connection.
        $agents = $this->agents($scope)->whereIn('id', $rows->pluck('agent_id')->all())->pluck('name', 'id');
        $workspaces = $this->workspaceAccounts()->whereIn('id', $rows->pluck('workspace_id')->unique()->all())->pluck('name', 'id');

        return $rows->map(fn (object $row): TopAgentData => new TopAgentData(
            agentId: (int) $row->agent_id,
            name: $agents[$row->agent_id] ?? null,
            workspaceId: (int) $row->workspace_id,
            workspaceName: $workspaces[$row->workspace_id] ?? null,
            messages: (int) $row->messages,
            conversations: (int) $row->conversations,
        ))->values()->all();
    }
}
