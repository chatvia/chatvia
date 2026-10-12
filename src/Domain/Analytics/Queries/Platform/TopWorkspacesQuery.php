<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Platform;

use Domain\Analytics\Data\Platform\PlatformScopeData;
use Domain\Analytics\Data\Platform\TopWorkspaceData;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * The workspaces that sent the most messages in the window, busiest first,
 * with their conversations and how many agents they have now.
 */
class TopWorkspacesQuery extends PlatformAnalyticsQuery
{
    /**
     * @return list<TopWorkspaceData>
     */
    public function get(PlatformScopeData $scope, int $limit = 10): array
    {
        $rows = $this->rollups($scope)
            ->groupBy('workspace_id')
            ->selectRaw('workspace_id, SUM(user_messages + assistant_messages) messages, SUM(conversations_started) conversations')
            ->havingRaw('SUM(user_messages + assistant_messages) > 0')
            ->orderByDesc('messages')
            ->orderBy('workspace_id')
            ->limit($limit)
            ->get();

        $ids = $rows->pluck('workspace_id')->all();

        // Names live on the main DB; resolve them in PHP so this works even
        // when analytics runs on a separate connection.
        $workspaces = $this->workspaceAccounts()
            ->leftJoin('workspace_user as wu', fn (JoinClause $join): JoinClause => $join->on('wu.workspace_id', '=', 'workspaces.id')->where('wu.role', 'owner'))
            ->leftJoin('users as owner', 'owner.id', '=', 'wu.user_id')
            ->whereIn('workspaces.id', $ids)
            ->get(['workspaces.id', 'workspaces.name', 'owner.email'])
            ->keyBy('id');
        $agents = DB::table('agents')->whereIn('workspace_id', $ids)->groupBy('workspace_id')->selectRaw('workspace_id, COUNT(*) total')->pluck('total', 'workspace_id');

        return $rows->map(fn (object $row): TopWorkspaceData => new TopWorkspaceData(
            workspaceId: (int) $row->workspace_id,
            name: $workspaces->get($row->workspace_id)?->name,
            email: $workspaces->get($row->workspace_id)?->email,
            messages: (int) $row->messages,
            conversations: (int) $row->conversations,
            agents: (int) ($agents[$row->workspace_id] ?? 0),
        ))->values()->all();
    }
}
