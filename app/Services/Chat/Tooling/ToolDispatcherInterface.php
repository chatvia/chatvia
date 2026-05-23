<?php

namespace App\Services\Chat\Tooling;

use App\Models\Agent;

interface ToolDispatcherInterface
{
    /**
     * Execute a tool call by name for the given agent, returning a string
     * payload suitable to pass back to the LLM as a `role: 'tool'` message.
     *
     * Implementations must never throw — error conditions are returned as
     * human-readable strings so the LLM can recover (e.g. "Tool error:
     * timeout after 10s").
     *
     * @param  string  $arguments  JSON-encoded args from the LLM
     */
    public function dispatch(Agent $agent, string $name, string $arguments): string;
}
