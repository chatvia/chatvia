<?php

namespace Domain\Conversation\Services\Tooling\Dispatcher;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Routing\ToolRouter;
use Domain\Integration\Mcp\Services\McpToolDispatcher;

/**
 * The single `ToolDispatcherInterface` binding the rest of the app talks to.
 * It looks at the tool name and forwards to the right backend:
 *   - `mcp__{slug}__{tool}` → McpToolDispatcher
 *   - anything else        → HttpToolDispatcher (webhook AgentFunctions)
 */
class RoutingToolDispatcher implements ToolDispatcherInterface
{
    public function __construct(
        private readonly ToolRouter $router,
        private readonly HttpToolDispatcher $http,
        private readonly McpToolDispatcher $mcp,
    ) {}

    public function dispatch(Agent $agent, string $name, string $arguments, ?string $conversationExternalId = null, ?string $chatterExternalId = null): ToolDispatchResult
    {
        return $this->router->isMcp($name)
            ? $this->mcp->dispatch($agent, $name, $arguments, $conversationExternalId, $chatterExternalId)
            : $this->http->dispatch($agent, $name, $arguments, $conversationExternalId, $chatterExternalId);
    }
}
