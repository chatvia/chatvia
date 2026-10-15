<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Routing\ConnectorClassifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function attachClassifierConnector(Agent $agent, string $slug, string $name, string $description): void
{
    AgentMcpServer::factory()->preset($slug)->attachedTo($agent)->create(['name' => $name, 'description' => $description]);
}

/** A classifier whose model answers with a fixed reply. */
function classifierReturning(string $content): ConnectorClassifier
{
    fakeLlmCompletion($content);

    return app(ConnectorClassifier::class);
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

it('fails open (null) when the provider fails', function (): void {
    $agent = Agent::factory()->create();
    attachClassifierConnector($agent, 'zendesk', 'Zendesk', 'Customer support');

    config(['services.scalewayai.api_key' => 'test-key']);
    Http::fake(['*' => Http::response(['error' => 'llm down'], 503)]);

    expect(app(ConnectorClassifier::class)->classify($agent, 'open a ticket'))->toBeNull();
});

it('asks for a JSON reply limited to the reachable connectors', function (): void {
    $agent = Agent::factory()->create();
    attachClassifierConnector($agent, 'zendesk', 'Zendesk', 'Customer support');

    classifierReturning('{"connectors":["zendesk"]}')->classify($agent, 'open a ticket');

    Http::assertSent(fn (Request $request): bool => $request['response_format']['json_schema']['schema']['properties']['connectors']['items']['enum'] === ['zendesk']
        && (float) $request['temperature'] === 0.0);
});

it('fails open (null) on an unparseable response', function (): void {
    $agent = Agent::factory()->create();
    attachClassifierConnector($agent, 'zendesk', 'Zendesk', 'Customer support');

    expect(classifierReturning('not json at all')->classify($agent, 'open a ticket'))->toBeNull();
});
