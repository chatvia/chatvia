<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Routing\ConnectorClassifier;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Before tool search, a small model may pick which of the agent's connectors
 * a message needs. It may only pick reachable ones, and whenever it picks
 * nothing, fails or answers nonsense, nothing is pruned.
 */
function ccAttachConnector(Agent $agent, string $slug, string $name, string $description): void
{
    AgentMcpServer::factory()->preset($slug)->attachedTo($agent)->create(['name' => $name, 'description' => $description]);
}

/** A classifier whose model answers with a fixed reply. */
function ccReturning(string $content): ConnectorClassifier
{
    fakeLlmCompletion($content);

    return app(ConnectorClassifier::class);
}

it('returns the picked connectors, constrained to the reachable set', function (): void {
    $agent = Agent::factory()->create();
    ccAttachConnector($agent, 'zendesk', 'Zendesk', 'Customer support tickets');
    ccAttachConnector($agent, 'linear', 'Linear', 'Engineering issue tracking');

    // 'ghost' is not a reachable connector — it must be filtered out.
    $picked = ccReturning('{"connectors":["zendesk","ghost"]}')
        ->classify($agent, 'open a support ticket');

    expect($picked)->toBe(['zendesk']);
});

it('returns null (prune nothing) when the model picks no connector', function (): void {
    $agent = Agent::factory()->create();
    ccAttachConnector($agent, 'zendesk', 'Zendesk', 'Customer support');

    expect(ccReturning('{"connectors":[]}')->classify($agent, 'hello there'))->toBeNull();
});

it('fails open (null) when the provider fails', function (): void {
    $agent = Agent::factory()->create();
    ccAttachConnector($agent, 'zendesk', 'Zendesk', 'Customer support');

    connectAiProviders(AiProvider::Scaleway);
    Http::fake(['*' => Http::response(['error' => 'llm down'], 503)]);

    expect(app(ConnectorClassifier::class)->classify($agent, 'open a ticket'))->toBeNull();
});

it('asks for a JSON reply limited to the reachable connectors', function (): void {
    $agent = Agent::factory()->create();
    ccAttachConnector($agent, 'zendesk', 'Zendesk', 'Customer support');

    ccReturning('{"connectors":["zendesk"]}')->classify($agent, 'open a ticket');

    Http::assertSent(fn (Request $request): bool => $request['response_format']['json_schema']['schema']['properties']['connectors']['items']['enum'] === ['zendesk']
        && (float) $request['temperature'] === 0.0);
});

it('fails open (null) on an unparseable response', function (): void {
    $agent = Agent::factory()->create();
    ccAttachConnector($agent, 'zendesk', 'Zendesk', 'Customer support');

    expect(ccReturning('not json at all')->classify($agent, 'open a ticket'))->toBeNull();
});
