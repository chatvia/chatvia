<?php

declare(strict_types=1);

namespace Domain\Agent\QueryBuilders;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query scopes for agents. Analytics about an agent are not here — they live
 * in the Analytics read models (`Domain\Analytics\Queries\Agent`), which
 * read the analytics tables.
 *
 * @template TModel of Agent
 *
 * @extends Builder<TModel>
 */
class AgentQueryBuilder extends Builder {}
