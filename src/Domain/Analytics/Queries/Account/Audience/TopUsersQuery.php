<?php

namespace Domain\Analytics\Queries\Account\Audience;

use App\Identity\Chatter\Models\Chatter;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Audience\TopUserData;
use Domain\Analytics\Data\Account\Audience\TopUsersData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Analytics\Queries\Account\Concerns\ReadsRollups;
use Domain\Conversation\Enums\MessageRole;
use Illuminate\Database\Query\Builder;

/**
 * The most active identified end-users (chatters) by messages sent, plus the
 * number of distinct identified users and of new visitors. Anonymous widget
 * visitors are not named but count as new visitors. Messages per chatter
 * come from the raw events the prune still keeps.
 */
class TopUsersQuery extends AccountAnalyticsQuery
{
    use ReadsRollups;

    /**
     * @param  int  $limit  how many chatters to name; at least 1, since the
     *                      distinct count is read from the first row
     */
    public function get(AnalyticsScopeData $scope, int $limit): TopUsersData
    {
        // One pass: the window count runs over the grouped rows before the
        // LIMIT, so it is the number of distinct chatters in the range.
        $rows = $this->identifiedMessages($scope)
            ->selectRaw('chatter_id, COUNT(*) messages, COUNT(*) OVER () unique_users')
            ->groupBy('chatter_id')
            ->orderByDesc('messages')
            ->limit($limit)
            ->get();

        $users = Chatter::query()
            ->whereIn('id', $rows->pluck('chatter_id')->all())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        return new TopUsersData(
            topUsers: $rows->map(static fn (object $row): TopUserData => new TopUserData(
                name: $users[$row->chatter_id]->name ?? 'Unknown user',
                email: $users[$row->chatter_id]->email ?? null,
                messages: (int) $row->messages,
            ))->all(),
            uniqueIdentifiedUsers: (int) ($rows->first()->unique_users ?? 0),
            newVisitors: (int) $this->sessionFirsts($scope)->count(),
        );
    }

    /** Messages sent by signed-in chatters in the window. */
    private function identifiedMessages(AnalyticsScopeData $scope): Builder
    {
        return $this->messageEvents($scope, $this->rawEventPeriod($scope))
            ->where('role', MessageRole::User->value)
            ->whereNotNull('chatter_id');
    }
}
