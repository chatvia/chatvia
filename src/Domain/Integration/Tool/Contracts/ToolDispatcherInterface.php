<?php

namespace Domain\Integration\Tool\Contracts;

use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Data\ToolCallData;
use Domain\Integration\Tool\Data\ToolDispatchResult;
use Domain\Integration\Tool\Dispatch\RoutingToolDispatcher;

/**
 * Runs one LLM-requested tool call for an agent. The app binds this to
 * {@see RoutingToolDispatcher}, which picks the MCP or webhook backend from
 * the tool name.
 */
interface ToolDispatcherInterface
{
    /**
     * Execute a tool call for the given agent. Returns a `ToolDispatchResult`
     * carrying both the text the LLM will see on its next turn and any
     * structured artifacts the chat UI should render inline (charts, etc.).
     *
     * Implementations must never throw — error conditions are returned as
     * human-readable strings in `text` so the LLM can recover (e.g. "Tool
     * error: timeout after 10s") with an empty `artifacts` array.
     */
    public function dispatch(Agent $agent, ToolCallData $call): ToolDispatchResult;
}
