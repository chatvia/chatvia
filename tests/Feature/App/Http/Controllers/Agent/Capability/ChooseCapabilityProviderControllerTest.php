<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Services\CapabilityProvider;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Testing\TestResponse;

/**
 * PUT agents/{agent}/capabilities/{slug}/provider picks which connected
 * scheduler the agent's Calendar books through.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
    foreach (['calendly', 'cal-com'] as $slug) {
        AgentMcpServer::factory()->forOwner($this->owner)->create(['connector_slug' => $slug, 'slug' => $slug, 'is_enabled' => true]);
    }
});

function ccpcChoose(User $user, string $agentId, string $slug, string $provider): TestResponse
{
    return test()->actingAs($user)->putJson(route('agents.capabilities.provider', ['agent' => $agentId, 'slug' => $slug]), ['provider' => $provider]);
}

it('saves the scheduler the agent books through', function (): void {
    ccpcChoose($this->owner, $this->agent->external_id, 'calendar', 'cal-com')->assertOk()->assertJsonPath('ok', true);

    expect($this->agent->fresh()->settings(CapabilityProvider::SETTINGS_GROUP)->get('calendar'))->toBe('cal-com');
});

it('refuses a scheduler that is not connected or not one', function (string $provider): void {
    AgentMcpServer::query()->where('connector_slug', 'cal-com')->update(['last_error' => 'Token revoked']);

    ccpcChoose($this->owner, $this->agent->external_id, 'calendar', $provider)->assertUnprocessable();
})->with(['not connected' => ['cal-com'], 'not a scheduler' => ['stripe']]);

it('404s for a capability that has no providers', function (): void {
    ccpcChoose($this->owner, $this->agent->external_id, 'forms', 'calendly')->assertNotFound();
});

it('needs a provider', function (): void {
    $this->actingAs($this->owner)
        ->putJson(route('agents.capabilities.provider', ['agent' => $this->agent->external_id, 'slug' => 'calendar']), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['provider']);
});
