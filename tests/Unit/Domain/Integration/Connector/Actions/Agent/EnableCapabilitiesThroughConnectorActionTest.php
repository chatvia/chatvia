<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\Agent\EnableCapabilitiesThroughConnectorAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The capabilities that work through a connector turn on for the agent it was
 * attached to; a custom server, with no catalog slug, turns nothing on.
 */
beforeEach(function (): void {
    // Capabilities and forms need a license key; EnsureCapabilityLicenseTest covers the gate.
    withLicense();
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
    $this->calendar = AgentMcpServer::factory()->forOwner($this->owner)->builtin('calendar')->create();
});

it('turns on Calendar for Calendly', function (): void {
    $calendly = AgentMcpServer::factory()->forOwner($this->owner)->create(['connector_slug' => 'calendly', 'slug' => 'calendly', 'is_enabled' => true]);

    app(EnableCapabilitiesThroughConnectorAction::class)->execute($this->agent, $calendly);

    expect($this->agent->mcpServers()->whereKey($this->calendar->id)->exists())->toBeTrue();
});

it('turns nothing on for a custom server', function (): void {
    app(EnableCapabilitiesThroughConnectorAction::class)->execute($this->agent, AgentMcpServer::factory()->forOwner($this->owner)->create());

    expect($this->agent->mcpServers()->exists())->toBeFalse();
});
