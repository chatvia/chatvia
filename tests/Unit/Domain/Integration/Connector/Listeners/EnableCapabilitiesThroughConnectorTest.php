<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Services\CapabilityProvider;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Attaching Calendly or Cal.com to an agent turns Calendar on for it and makes
 * the one attached last the service it books through; the connector's own
 * tools stay attached. A switched-off connector and other connectors change
 * nothing. Capabilities need a license key (EnsureCapabilityLicenseTest
 * covers the gate), and the workspace's Calendar row exists, so enabling is a
 * pure attach with no remote tools/list during the test.
 */
beforeEach(function (): void {
    withLicense();
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
    $this->calendar = AgentMcpServer::factory()->forOwner($this->owner)->builtin('calendar')->create();
});

function ectcConnect(User $owner, string $slug, array $attributes = []): AgentMcpServer
{
    return AgentMcpServer::factory()->forOwner($owner)->create(['connector_slug' => $slug, 'slug' => $slug, 'is_enabled' => true, ...$attributes]);
}

it('turns Calendar on through the booking connector attached last, keeping its own tools', function (): void {
    $calendly = ectcConnect($this->owner, 'calendly');
    $calcom = ectcConnect($this->owner, 'cal-com');

    $this->agent->attachConnector($calendly);
    $this->agent->attachConnector($calcom);

    expect($this->agent->mcpServers()->pluck('mcp_servers.id')->sort()->values()->all())->toBe(collect([$this->calendar->id, $calendly->id, $calcom->id])->sort()->values()->all())
        ->and($this->agent->fresh()->settings(CapabilityProvider::SETTINGS_GROUP)->get('calendar'))->toBe('cal-com');
});

it('leaves Calendar off for a switched-off booking connector or another connector', function (): void {
    $this->agent->attachConnector(ectcConnect($this->owner, 'calendly', ['is_enabled' => false]));
    $this->agent->attachConnector(ectcConnect($this->owner, 'stripe'));

    expect($this->agent->mcpServers()->whereKey($this->calendar->id)->exists())->toBeFalse();
});
