<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A deactivated workspace's agents stop answering on the hosted chat: every
 * chat route shows the same AgentNotFound screen a switched-off Hosted Chat
 * gets, so nothing reaches the voice provider or the model. Reactivating the
 * workspace brings the chat back.
 */
beforeEach(function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'auto']);
    Http::fake();

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('allow_voice_conversation', true, 'appearance');
    $this->chat = '/chat/'.$this->agent->slug;
});

it('serves the hosted chat while the workspace is active', function (): void {
    $this->get($this->chat)
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/Home'));
});

it('shows AgentNotFound on every chat route once the workspace is deactivated', function (string $method, string $path, array $body): void {
    deactivateWorkspaceOf($this->agent);

    $this->call($method, $this->chat.$path, $body)
        ->assertInertia(fn (Assert $page): Assert => $page->component('agents/chat/AgentNotFound'));

    Http::assertNotSent(fn (Request $request): bool => Str::contains($request->url(), 'api.openai.com'));
})->with([
    'home' => ['GET', '', []],
    'stream' => ['POST', '/stream/'.Str::repeat('a', 8), ['content' => 'Hello']],
    'voice call' => ['POST', '/voice', ['sdp' => 'v=0 offer']],
    'usage' => ['GET', '/usage', []],
]);

it('serves the hosted chat again once the workspace is reactivated', function (): void {
    deactivateWorkspaceOf($this->agent);
    reactivateWorkspaceOf($this->agent);

    $this->get($this->chat)
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/Home'));
});
