<?php

use App\Http\Middleware\Chat\EnsureChatIsAccessible;
use App\Http\Middleware\Chat\ResolveHostedChatAgent;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Every hosted chat lives at /chat/{agentSlug}: the agent's slug, or its
 * external id for the permanent and embed addresses.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create([
        'is_public' => true,
        'slug' => Str::lower(Str::random(12)),
    ]);
});

it('serves the chat under the agent slug', function (): void {
    $this->get('/chat/'.$this->agent->slug)
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/pages/Home')->where('agentSlug', $this->agent->slug));
});

it('serves the chat under the agent external id', function (): void {
    $this->get('/chat/'.$this->agent->external_id)
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/pages/Home')->where('agentSlug', $this->agent->external_id));
});

it('shows the not-found page for an unknown agent', function (): void {
    $this->get('/chat/does-not-exist')
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/pages/AgentNotFound'));
});

it('sends a guest of a private agent to that agent\'s own login page', function (): void {
    $this->agent->update(['is_public' => false]);

    $this->get('/chat/'.$this->agent->slug)
        ->assertRedirect('/chat/'.$this->agent->slug.'/login');
});

it('resolves the agent once, before the access check, on every chat route', function (): void {
    $middleware = collect(app('router')->gatherRouteMiddleware(Route::getRoutes()->getByName('chat.home')));

    expect($middleware->filter(fn (mixed $m): bool => $m === ResolveHostedChatAgent::class))->toHaveCount(1)
        ->and($middleware->search(ResolveHostedChatAgent::class))
        ->toBeLessThan($middleware->search(EnsureChatIsAccessible::class));
});

it('passes route parameters after the agent segment to the controller in order', function (): void {
    // The controller takes `string $conversationId` by position, so the agent
    // segment must not reach it in that place.
    $conversationId = (string) Str::uuid();

    $this->get('/chat/'.$this->agent->slug.'/c/'.$conversationId)
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/pages/Chat')
            ->where('conversation_id', $conversationId)
            ->where('agent_id', $this->agent->id));
});

it('serves every agent on any host', function (): void {
    $this->get('http://192.168.1.10/chat/'.$this->agent->slug)
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/pages/Home'));
});
