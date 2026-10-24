<?php

use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentUrlBuilder;
use Illuminate\Support\Uri;

/**
 * Every agent's hosted chat lives under `{APP_URL}/chat/`: its slug for the
 * friendly address, its external id for the permanent and embed addresses. The
 * scheme and port of APP_URL are kept (a local install runs on a port).
 */
it('builds the hosted, permanent and embed URLs under the app URL', function (): void {
    config(['app.url' => 'https://chatvia.example']);
    $agent = Agent::factory()->make(['slug' => 'helper', 'external_id' => 'abc123']);

    expect(AgentUrlBuilder::chatBaseUrl())->toBe('https://chatvia.example/chat')
        ->and(AgentUrlBuilder::hostedUrl($agent))->toBe('https://chatvia.example/chat/helper')
        ->and(AgentUrlBuilder::permanentUrl($agent))->toBe('https://chatvia.example/chat/abc123')
        ->and(AgentUrlBuilder::chatUrl($agent))->toBe('https://chatvia.example/chat/helper')
        ->and(AgentUrlBuilder::chatEmbedUrl($agent))->toBe('https://chatvia.example/chat/abc123/embed');
});

it('keeps the port of the app URL', function (): void {
    config(['app.url' => 'http://localhost:8000/']);
    $agent = Agent::factory()->make(['slug' => 'helper', 'external_id' => 'abc123']);

    expect($agent->url)->toBe('http://localhost:8000/chat/helper')
        ->and($agent->chat_embed_url)->toBe('http://localhost:8000/chat/abc123/embed');
});

it('falls back to the external id when an agent has no slug', function (): void {
    config(['app.url' => 'https://chatvia.example']);
    $agent = Agent::factory()->make(['slug' => null, 'external_id' => 'abc123']);

    expect($agent->chat_url)->toBe('https://chatvia.example/chat/abc123');
});

it('matches the chat routes', function (): void {
    config(['app.url' => 'https://chatvia.example']);
    $agent = Agent::factory()->make(['slug' => 'helper', 'external_id' => 'abc123']);

    expect(Uri::of(AgentUrlBuilder::chatUrl($agent))->getUri()->getPath())->toBe(route('chat.home', ['agentSlug' => 'helper'], absolute: false))
        ->and(Uri::of(AgentUrlBuilder::chatEmbedUrl($agent))->getUri()->getPath())->toBe(route('chat.embed.home', ['agentSlug' => 'abc123'], absolute: false));
});
