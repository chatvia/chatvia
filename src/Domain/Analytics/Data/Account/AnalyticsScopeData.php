<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Account;

use App\Identity\Workspace\Models\Workspace;
use Domain\Analytics\Services\Period;

/**
 * The resolved scope of one account-analytics question: whose numbers, over
 * which window, and for which agents.
 *
 * {@see $agentId} narrows the analytics rollups (null = the whole account);
 * {@see $agentIds} narrows the live chat tables. An agent the workspace does
 * not own narrows both to {@see UNKNOWN_AGENT}, which matches nothing, so the
 * answer is zero rather than the whole account.
 */
final readonly class AnalyticsScopeData
{
    /** An agent id no row has: the scope of an agent that is not the workspace's. */
    public const int UNKNOWN_AGENT = -1;

    /**
     * @param  list<int>  $agentIds
     */
    public function __construct(
        public Workspace $workspace,
        public Period $period,
        public ?int $agentId,
        public array $agentIds,
    ) {}
}
