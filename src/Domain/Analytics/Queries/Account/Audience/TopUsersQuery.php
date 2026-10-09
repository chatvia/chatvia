<?php

namespace Domain\Analytics\Queries\Account\Audience;

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Audience\TopUserData;
use Domain\Analytics\Data\Account\Audience\TopUsersData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Illuminate\Database\Query\Builder;

/**
 * The most active identified end-users (chatters) by messages sent, plus the
 * number of distinct identified users and of new visitors. Anonymous widget
 * visitors are not named but count as new visitors.
 */
class TopUsersQuery extends AccountAnalyticsQuery
{
    public function get(AnalyticsScopeData $scope, int $limit): TopUsersData
    {
        $rows = $this->identifiedMessages($scope)
            ->selectRaw('chatter_user_id, COUNT(*) messages')
            ->groupBy('chatter_user_id')
            ->orderByDesc('messages')
            ->limit($limit)
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('chatter_user_id')->all())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        return new TopUsersData(
            topUsers: $rows->map(static fn (object $row): TopUserData => new TopUserData(
                name: $users[$row->chatter_user_id]->name ?? 'Unknown user',
                email: $users[$row->chatter_user_id]->email ?? null,
                messages: (int) $row->messages,
            ))->all(),
            uniqueIdentifiedUsers: (int) $this->identifiedMessages($scope)->distinct()->count('chatter_user_id'),
            newVisitors: (int) $this->sessionFirsts($scope)->count(),
        );
    }

    /** Messages sent by signed-in chatters in the window. */
    private function identifiedMessages(AnalyticsScopeData $scope): Builder
    {
        return $this->messageEvents($scope)
            ->where('role', 'user')
            ->whereNotNull('chatter_user_id');
    }
}
