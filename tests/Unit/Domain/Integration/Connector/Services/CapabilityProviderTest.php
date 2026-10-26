<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Services\CapabilityProvider;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * A capability that works through several connectors uses the agent's pick
 * while it is connected, otherwise the first connected one in catalog order.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->calendar = app(ConnectorCatalog::class)->capabilities()->get('calendar');
});

function cppConnect(Agent $agent, string $slug, array $attributes = []): AgentMcpServer
{
    return AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id, 'connector_slug' => $slug, 'slug' => $slug, 'is_enabled' => true, ...$attributes]);
}

it('is not met with nothing connected', function (): void {
    $providers = app(CapabilityProvider::class);

    expect($providers->isMet($this->agent, $this->calendar))->toBeFalse()
        ->and($providers->providerFor($this->agent, $this->calendar))->toBeNull();
});

it('uses the first connected one when the agent picked none', function (): void {
    cppConnect($this->agent, 'cal-com');

    expect(app(CapabilityProvider::class)->providerFor($this->agent, $this->calendar))->toBe('cal-com');
});

it('uses the agent\'s pick while it is connected', function (): void {
    cppConnect($this->agent, 'calendly');
    $calcom = cppConnect($this->agent, 'cal-com');
    $this->agent->setSetting(key: 'calendar', value: 'cal-com', group: CapabilityProvider::SETTINGS_GROUP);

    expect(app(CapabilityProvider::class)->providerFor($this->agent, $this->calendar))->toBe('cal-com');

    $calcom->update(['last_error' => 'Token revoked']);

    expect(app(CapabilityProvider::class)->providerFor($this->agent, $this->calendar))->toBe('calendly');
});

it('ignores another workspace\'s connection', function (): void {
    cppConnect(Agent::factory()->create(), 'calendly');

    expect(app(CapabilityProvider::class)->connected($this->agent, $this->calendar))->toBe([]);
});

it('is always met for a capability that works through nothing', function (): void {
    expect(app(CapabilityProvider::class)->isMet($this->agent, app(ConnectorCatalog::class)->capabilities()->get('forms')))->toBeTrue();
});
