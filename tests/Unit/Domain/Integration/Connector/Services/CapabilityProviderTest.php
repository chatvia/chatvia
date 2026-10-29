<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Services\CapabilityProvider;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * A capability that works through several connectors uses the agent's pick
 * while it is connected, otherwise the first connected one in catalog order,
 * and only ever the agent's own workspace's connections.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->calendar = app(ConnectorCatalog::class)->capabilities()->get('calendar');
});

function cppConnect(Agent $agent, string $slug, array $attributes = []): AgentMcpServer
{
    return AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id, 'connector_slug' => $slug, 'slug' => $slug, 'is_enabled' => true, ...$attributes]);
}

it('is not met with nothing connected, nor through another workspace’s connection', function (): void {
    cppConnect(Agent::factory()->create(), 'calendly');
    $providers = app(CapabilityProvider::class);

    expect($providers->isMet($this->agent, $this->calendar))->toBeFalse()
        ->and($providers->providerFor($this->agent, $this->calendar))->toBeNull()
        ->and($providers->connected($this->agent, $this->calendar))->toBe([]);
});

it('uses the agent\'s pick while it is connected, else the first connected one', function (): void {
    cppConnect($this->agent, 'calendly');
    $calcom = cppConnect($this->agent, 'cal-com');
    $this->agent->setSetting(key: 'calendar', value: 'cal-com', group: CapabilityProvider::SETTINGS_GROUP);

    $picked = app(CapabilityProvider::class)->providerFor($this->agent, $this->calendar);
    $calcom->update(['last_error' => 'Token revoked']);
    $fallback = app(CapabilityProvider::class)->providerFor($this->agent, $this->calendar);

    expect($picked)->toBe('cal-com')
        ->and($fallback)->toBe('calendly');
});
