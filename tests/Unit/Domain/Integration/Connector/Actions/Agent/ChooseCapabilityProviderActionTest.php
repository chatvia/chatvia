<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\ChooseCapabilityProviderAction;
use Domain\Integration\Connector\Exceptions\CapabilityProviderUnavailableException;
use Domain\Integration\Connector\Services\CapabilityProvider;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The agent's owner picks which connected scheduler Calendar books through;
 * one that is not connected, or not a scheduler, cannot be picked.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->calendar = app(ConnectorCatalog::class)->capabilities()->get('calendar');
    foreach (['calendly', 'cal-com'] as $slug) {
        AgentMcpServer::factory()->create(['workspace_id' => $this->agent->workspace_id, 'connector_slug' => $slug, 'slug' => $slug, 'is_enabled' => true]);
    }
});

it('stores the pick as the agent\'s setting', function (): void {
    app(ChooseCapabilityProviderAction::class)->execute($this->agent, $this->calendar, 'cal-com');

    expect($this->agent->fresh()->settings(CapabilityProvider::SETTINGS_GROUP)->get('calendar'))->toBe('cal-com');
});

it('refuses a connector the capability does not work through', function (): void {
    app(ChooseCapabilityProviderAction::class)->execute($this->agent, $this->calendar, 'google-calendar');
})->throws(CapabilityProviderUnavailableException::class);

it('refuses a scheduler the workspace has not connected', function (): void {
    AgentMcpServer::query()->where('connector_slug', 'cal-com')->update(['is_enabled' => false]);

    app(ChooseCapabilityProviderAction::class)->execute($this->agent, $this->calendar, 'cal-com');
})->throws(CapabilityProviderUnavailableException::class);
