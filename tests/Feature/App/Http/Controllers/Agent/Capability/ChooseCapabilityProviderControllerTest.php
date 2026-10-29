<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Services\CapabilityProvider;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * PUT agents/{agent}/capabilities/{slug}/provider picks which connected
 * scheduler the agent's Calendar books through.
 */
it('saves a connected scheduler and refuses one that is not connected', function (): void {
    withLicense();
    $owner = User::factory()->create();
    $agent = makeTeamAgent($owner, 'Support Bot');
    AgentMcpServer::factory()->forOwner($owner)->create(['connector_slug' => 'calendly', 'slug' => 'calendly', 'is_enabled' => true]);
    AgentMcpServer::factory()->forOwner($owner)->create(['connector_slug' => 'cal-com', 'slug' => 'cal-com', 'is_enabled' => true, 'last_error' => 'Token revoked']);
    $url = route('agents.capabilities.provider', ['agent' => $agent->external_id, 'slug' => 'calendar']);

    $this->actingAs($owner)->putJson($url, ['provider' => 'cal-com'])->assertUnprocessable();

    expect($agent->fresh()->settings(CapabilityProvider::SETTINGS_GROUP)->get('calendar'))->toBeNull();

    $this->actingAs($owner)->putJson($url, ['provider' => 'calendly'])->assertOk();

    expect($agent->fresh()->settings(CapabilityProvider::SETTINGS_GROUP)->get('calendar'))->toBe('calendly');
});
