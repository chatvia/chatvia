<?php

use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentUrlBuilder;

/**
 * Agent URLs live on a subdomain of the hosting base URL. The scheme and the
 * port of that base URL are kept (a local install runs on a port).
 */
it('builds the hosted and permanent URLs on the app URL host', function (): void {
    config(['app.url' => 'https://chatvia.example', 'chatvia.agent.hosting_url' => null]);
    $agent = Agent::factory()->make(['sub_domain' => 'helper', 'external_id' => 'abc123']);

    expect(AgentUrlBuilder::hostedUrl($agent))->toBe('https://helper.chatvia.example')
        ->and(AgentUrlBuilder::permanentUrl($agent))->toBe('https://abc123.chatvia.example')
        ->and(AgentUrlBuilder::chatUrl($agent))->toBe('https://helper.chatvia.example/chat')
        ->and(AgentUrlBuilder::chatEmbedUrl($agent))->toBe('https://abc123.chatvia.example/chat/embed');
});

it('keeps the port of the base URL', function (): void {
    config(['app.url' => 'http://localhost:8000', 'chatvia.agent.hosting_url' => null]);
    $agent = Agent::factory()->make(['sub_domain' => 'helper', 'external_id' => 'abc123']);

    expect($agent->url)->toBe('http://helper.localhost:8000')
        ->and($agent->chat_embed_url)->toBe('http://abc123.localhost:8000/chat/embed');
});

it('uses the configured hosting URL instead of the app URL', function (): void {
    config(['app.url' => 'https://dashboard.example', 'chatvia.agent.hosting_url' => 'https://agents.example:8443']);
    $agent = Agent::factory()->make(['sub_domain' => 'helper', 'external_id' => 'abc123']);

    expect($agent->url)->toBe('https://helper.agents.example:8443');
});
