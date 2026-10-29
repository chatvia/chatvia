<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The hosted chat closes as if the agent did not exist: when its Hosted Chat
 * surface is turned off on the Deploy page, and on every chat route while its
 * workspace is deactivated, so nothing reaches the voice provider or the
 * model.
 */
beforeEach(function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'auto']);
    Http::fake();

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('allow_voice_conversation', true, 'appearance');
    $this->chat = '/chat/'.$this->agent->slug;
});

it('shows AgentNotFound when the hosted chat surface is turned off', function (): void {
    $this->agent->setSetting(key: 'hosted_chat', value: false, group: 'surfaces');

    $this->get($this->chat)
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/pages/AgentNotFound'));
});

it('shows AgentNotFound on every chat route once the workspace is deactivated', function (string $method, string $path, array $body): void {
    deactivateWorkspaceOf($this->agent);

    $this->call($method, $this->chat.$path, $body)
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/pages/AgentNotFound'));

    Http::assertNotSent(fn (Request $request): bool => Str::contains($request->url(), 'api.openai.com'));
})->with([
    'stream' => ['POST', '/stream/'.Str::repeat('a', 8), ['content' => 'Hello']],
    'voice call' => ['POST', '/voice', ['sdp' => 'v=0 offer']],
]);
