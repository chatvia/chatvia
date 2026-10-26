<?php

declare(strict_types=1);

namespace Domain\Booking\Services;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The workspace's Calendly connection the Calendar capability books through.
 * It is the workspace's Calendly connector row; it need not be attached to
 * the agent, so the model never sees Calendly's own tools through Calendar.
 */
class CalendlyConnection
{
    public const string CONNECTOR_SLUG = 'calendly';

    /** The usable connection: switched on and not failing. */
    public function serverFor(Agent $agent): ?AgentMcpServer
    {
        return AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('connector_slug', self::CONNECTOR_SLUG)
            ->where('is_enabled', true)
            ->whereNull('last_error')
            ->first();
    }

    public function isConnected(Agent $agent): bool
    {
        return $this->serverFor($agent) instanceof AgentMcpServer;
    }
}
