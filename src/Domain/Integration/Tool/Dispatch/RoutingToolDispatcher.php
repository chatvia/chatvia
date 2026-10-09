<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Dispatch;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Services\HttpToolDispatcher;
use Domain\Integration\Mcp\Services\McpToolDispatcher;
use Domain\Integration\Tool\Contracts\ToolDispatcherInterface;
use Domain\Integration\Tool\Data\ToolCallData;
use Domain\Integration\Tool\Data\ToolDispatchResult;

/**
 * The single `ToolDispatcherInterface` binding the rest of the app talks to.
 * It looks at the tool name and forwards to the right backend:
 *   - `mcp__{slug}__{tool}` → McpToolDispatcher
 *   - anything else        → HttpToolDispatcher (webhook AgentFunctions)
 */
class RoutingToolDispatcher implements ToolDispatcherInterface
{
    public function __construct(
        private readonly HttpToolDispatcher $http,
        private readonly McpToolDispatcher $mcp,
    ) {}

    public function dispatch(Agent $agent, ToolCallData $call): ToolDispatchResult
    {
        return $call->name->isMcp()
            ? $this->mcp->dispatch($agent, $call)
            : $this->http->dispatch($agent, $call);
    }
}
