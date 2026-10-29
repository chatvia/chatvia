<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Routing\ConnectorClassifier;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Support\Facades\Http;

/**
 * Before tool search, a small model may pick which of the agent's connectors
 * a message needs. It may only pick reachable ones (a picked connector the
 * agent cannot reach is filtered out), and whenever it picks nothing, fails
 * or answers nonsense, nothing is pruned.
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

    $picked = ccReturning('{"connectors":["zendesk","ghost"]}')
        ->classify($agent, 'open a support ticket');

    expect($picked)->toBe(['zendesk']);
});

it('prunes nothing (null) when the model picks nothing, answers nonsense or fails', function (Closure $classifier): void {
    $agent = Agent::factory()->create();
    ccAttachConnector($agent, 'zendesk', 'Zendesk', 'Customer support');

    expect($classifier()->classify($agent, 'open a ticket'))->toBeNull();
})->with([
    'no connector picked' => [fn (): ConnectorClassifier => ccReturning('{"connectors":[]}')],
    'an unparseable reply' => [fn (): ConnectorClassifier => ccReturning('not json at all')],
    'the provider fails' => [function (): ConnectorClassifier {
        connectAiProviders(AiProvider::Scaleway);
        Http::fake(['*' => Http::response(['error' => 'llm down'], 503)]);

        return app(ConnectorClassifier::class);
    }],
]);
