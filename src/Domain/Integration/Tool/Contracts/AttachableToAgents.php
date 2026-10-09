<?php

namespace Domain\Integration\Tool\Contracts;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A workspace-level tool source that agents opt into through a pivot — an MCP
 * server (`agent_mcp_server`) or a webhook function (`agent_function`). Lets
 * the "which agents use this?" screens and the tool-pool cache treat both the
 * same way.
 */
interface AttachableToAgents
{
    /**
     * The agents this tool source is attached to.
     *
     * @return BelongsToMany<Agent, covariant Model>
     */
    public function agents(): BelongsToMany;
}
