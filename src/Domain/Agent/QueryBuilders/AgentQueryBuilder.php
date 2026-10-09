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
     * Add total members count as a subquery select.
     *
     * @return self<TModel>
     */
    public function withTotalMembers(): self
    {
        return $this->addSelect([
            'total_members' => DB::table('users')
                ->whereColumn('users.workspace_id', 'agents.user_id')
                ->selectRaw('count(*)'),
        ]);
    }

    /**
     * Agents that have claimed a non-empty custom domain. The shared predicate
     * behind the custom-domain quota count — used by SetAgentDomainAction's
     * gate and the Setup page's quota display, so the two never drift apart.
     *
     * @return self<TModel>
     */
    public function withCustomDomain(): self
    {
        return $this->whereNotNull('domain')->where('domain', '!=', '');
    }
}
