<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Routing\ConnectorClassifier;
use Domain\Llm\Contracts\LlmProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;

function attachClassifierConnector(Agent $agent, string $slug, string $name, string $description): void
{
    $server = AgentMcpServer::create([
        'user_id' => $agent->user_id,
        'connector_slug' => $slug,
        'uses_central_catalog' => true,
        'name' => $name,
        'description' => $description,
        'slug' => $slug,
        'url' => "https://example.test/mcp/{$slug}",
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);

    DB::table('agent_mcp_server')->insert(['agent_id' => $agent->id, 'mcp_server_id' => $server->id]);
}

/** Build a classifier whose provider returns a fixed completion body. */
function classifierReturning(string $content): ConnectorClassifier
{
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('json')->with('choices.0.message.content')->andReturn($content);

    $provider = Mockery::mock(LlmProviderInterface::class);
    $provider->shouldReceive('completion')->andReturn($response);

    return new ConnectorClassifier($provider);
}

it('returns the picked connectors, constrained to the reachable set', function (): void {
    $agent = Agent::factory()->create();
    attachClassifierConnector($agent, 'zendesk', 'Zendesk', 'Customer support tickets');
    attachClassifierConnector($agent, 'linear', 'Linear', 'Engineering issue tracking');

    // 'ghost' is not a reachable connector — it must be filtered out.
    $picked = classifierReturning('{"connectors":["zendesk","ghost"]}')
        ->classify($agent, 'open a support ticket');

    expect($picked)->toBe(['zendesk']);
});

it('returns null (prune nothing) when the model picks no connector', function (): void {
    $agent = Agent::factory()->create();
    attachClassifierConnector($agent, 'zendesk', 'Zendesk', 'Customer support');

    expect(classifierReturning('{"connectors":[]}')->classify($agent, 'hello there'))->toBeNull();
});

it('fails open (null) when the provider throws', function (): void {
    $agent = Agent::factory()->create();
    attachClassifierConnector($agent, 'zendesk', 'Zendesk', 'Customer support');

    $provider = Mockery::mock(LlmProviderInterface::class);
    $provider->shouldReceive('completion')->andThrow(new RuntimeException('llm down'));

    expect(new ConnectorClassifier($provider)->classify($agent, 'open a ticket'))->toBeNull();
});

it('fails open (null) on an unparseable response', function (): void {
    $agent = Agent::factory()->create();
    attachClassifierConnector($agent, 'zendesk', 'Zendesk', 'Customer support');

    expect(classifierReturning('not json at all')->classify($agent, 'open a ticket'))->toBeNull();
});
