<?php

namespace Domain\Chat\Services\Tooling\Dispatcher;

use Domain\Agents\Models\Agent;

interface ToolDispatcherInterface
{
    /**
     * Execute a tool call by name for the given agent. Returns a
     * `ToolDispatchResult` carrying both the text the LLM will see on its
     * next turn and any structured artifacts the chat UI should render
     * inline (charts, etc.).
     *
     * Implementations must never throw — error conditions are returned as
     * human-readable strings in `text` so the LLM can recover (e.g. "Tool
     * error: timeout after 10s") with an empty `artifacts` array.
     *
     * @param  string  $arguments  JSON-encoded args from the LLM
     * @param  string|null  $conversationExternalId  External UUID of the active conversation, stamped on tool calls so receivers can scope writes (e.g. leads).
     */
    public function dispatch(Agent $agent, string $name, string $arguments, ?string $conversationExternalId = null): ToolDispatchResult;
}
