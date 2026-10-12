<?php

namespace Domain\Agent\QueryBuilders;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Query scopes for agents. Analytics about an agent are not here — they live
 * in the Analytics read models (`Domain\Analytics\Queries\Agent`), which
 * read the analytics tables.
 *
 * @template TModel of Agent
 *
 * @extends Builder<TModel>
 */
class AgentQueryBuilder extends Builder
{
    /**
     * Add total members count as a subquery select: the workspace's teammates
     * plus its chatters (who live in their own table).
     *
     * @return self<TModel>
     */
    public function withTotalMembers(): self
    {
        return $this->addSelect([
            'total_members' => DB::query()->selectRaw(
                '(select count(*) from workspace_user where workspace_user.workspace_id = agents.workspace_id)'
                .' + (select count(*) from chatters where chatters.workspace_id = agents.workspace_id)'
            ),
        ]);
    }
}
