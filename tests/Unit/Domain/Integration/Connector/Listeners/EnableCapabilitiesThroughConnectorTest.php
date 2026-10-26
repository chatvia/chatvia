<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Agent\Events\ConnectorAttachedToAgent;
use Domain\Integration\Connector\Services\CapabilityProvider;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Facades\Event;

/**
 * Attaching Calendly or Cal.com to an agent turns Calendar on for it and makes
 * the attached one the service it books through; the connector's own tools
 * stay attached. Other connectors change nothing.
 */
beforeEach(function (): void {
    // Capabilities and forms need a license key; EnsureCapabilityLicenseTest covers the gate.
    withLicense();
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
    // The workspace's Calendar row exists, so enabling is a pure attach (no
    // remote tools/list during the test).
    $this->calendar = AgentMcpServer::factory()->forOwner($this->owner)->builtin('calendar')->create();
});

function ectcConnect(User $owner, string $slug, array $attributes = []): AgentMcpServer
{
    return AgentMcpServer::factory()->forOwner($owner)->create(['connector_slug' => $slug, 'slug' => $slug, 'is_enabled' => true, ...$attributes]);
}

it('turns Calendar on when Cal.com is attached, keeping Cal.com\'s own tools', function (): void {
    $calcom = ectcConnect($this->owner, 'cal-com');

    $this->agent->attachConnector($calcom);

    expect($this->agent->mcpServers()->pluck('mcp_servers.id')->sort()->values()->all())->toBe(collect([$this->calendar->id, $calcom->id])->sort()->values()->all())
        ->and($this->agent->fresh()->settings(CapabilityProvider::SETTINGS_GROUP)->get('calendar'))->toBe('cal-com');
});

it('switches the booking service to the one just attached', function (): void {
    $this->agent->attachConnector(ectcConnect($this->owner, 'calendly'));
    $this->agent->attachConnector(ectcConnect($this->owner, 'cal-com'));

    expect($this->agent->fresh()->settings(CapabilityProvider::SETTINGS_GROUP)->get('calendar'))->toBe('cal-com');
});

it('leaves Calendar off for a connector that is switched off', function (): void {
    $this->agent->attachConnector(ectcConnect($this->owner, 'calendly', ['is_enabled' => false]));

    expect($this->agent->mcpServers()->whereKey($this->calendar->id)->exists())->toBeFalse();
});

it('changes nothing for another connector', function (): void {
    $this->agent->attachConnector(ectcConnect($this->owner, 'stripe'));

    expect($this->agent->mcpServers()->whereKey($this->calendar->id)->exists())->toBeFalse();
});

it('fires only for a new attachment', function (): void {
    Event::fake([ConnectorAttachedToAgent::class]);
    $calcom = ectcConnect($this->owner, 'cal-com');

    $this->agent->attachConnector($calcom);
    $this->agent->attachConnector($calcom);

    Event::assertDispatchedTimes(ConnectorAttachedToAgent::class, 1);
});
