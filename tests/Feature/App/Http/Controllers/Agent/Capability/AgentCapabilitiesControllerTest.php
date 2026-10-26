<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Inertia\Testing\AssertableInertia;

/**
 * An agent's Capabilities page lists the built-in capabilities it can switch
 * on (charts, forms and calendar; leads are part of forms), in catalog order,
 * each with whether it is on for the agent and whether the connector it works
 * through is connected.
 */
// Capabilities and forms need a license key; EnsureCapabilityLicenseTest covers the gate.
beforeEach(fn () => withLicense());

it('lists the capabilities in catalog order, with whether each is on', function (): void {
    $owner = User::factory()->create();
    $agent = makeTeamAgent($owner, 'Support Bot');
    AgentMcpServer::factory()->forOwner($owner)->builtin('charts')->create();

    $this->actingAs($owner)
        ->get(route('agents.capabilities', $agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('agents/Capabilities/index')
            // Ordered by catalog priority: charts (30), forms (40), calendar (45), then any others.
            ->where('capabilities.0.connector.slug', 'charts')
            ->where('capabilities.0.enabled', false)
            ->where('capabilities.0.ready', true)
            ->where('capabilities.1.connector.slug', 'forms')
            ->where('capabilities.2.connector.slug', 'calendar'));
});

it('marks Calendar not ready until the workspace connects Calendly or Cal.com', function (): void {
    $owner = User::factory()->create();
    $agent = makeTeamAgent($owner, 'Support Bot');

    $this->actingAs($owner)
        ->get(route('agents.capabilities', $agent->external_id))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('capabilities.2.ready', false)
            ->where('capabilities.2.provider', null)
            ->where('capabilities.2.providers.0.connector.slug', 'calendly')
            ->where('capabilities.2.providers.0.connected', false)
            ->where('capabilities.2.providers.1.connector.slug', 'cal-com'));

    AgentMcpServer::factory()->forOwner($owner)->create(['connector_slug' => 'cal-com', 'slug' => 'cal-com', 'is_enabled' => true]);

    $this->actingAs($owner)
        ->get(route('agents.capabilities', $agent->external_id))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('capabilities.2.ready', true)
            ->where('capabilities.2.provider', 'cal-com')
            ->where('capabilities.2.providers.1.connected', true));
});

it('marks Image generation unavailable until the admin picks an image service', function (string $driver, bool $available): void {
    config(['chatvia.ai.image_generator' => $driver]);
    $owner = User::factory()->create();
    $agent = makeTeamAgent($owner, 'Support Bot');

    $this->actingAs($owner)
        ->get(route('agents.capabilities', $agent->external_id))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($available): AssertableInertia {
            $rows = collect($page->toArray()['props']['capabilities']);

            expect($rows->firstWhere('connector.slug', 'image-generation')['connector']['available'])->toBe($available)
                ->and($rows->firstWhere('connector.slug', 'charts')['connector']['available'])->toBeTrue();

            return $page;
        });
})->with([
    'off' => ['none', false],
    'picked' => ['openai', true],
]);
