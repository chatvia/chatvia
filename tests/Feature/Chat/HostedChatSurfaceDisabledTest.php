<?php

use Domain\Agent\Models\Agent;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Hosted Chat surface can be turned off per agent from the Deploy page. When
 * it's disabled the chat 404s with the same AgentNotFound screen a non-existent
 * agent gets — "as if it doesn't exist".
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->chat = '/chat/'.$this->agent->slug;
});

it('serves the hosted chat when the surface is enabled (default)', function (): void {
    $this->get($this->chat)
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/Home'));
});

it('shows AgentNotFound when the hosted chat surface is disabled', function (): void {
    $this->agent->setSetting(key: 'hosted_chat', value: false, group: 'surfaces');

    $this->get($this->chat)
        ->assertInertia(fn (Assert $page): Assert => $page->component('agents/chat/AgentNotFound'));
});

it('serves the hosted chat again once the surface is re-enabled', function (): void {
    $this->agent->setSetting(key: 'hosted_chat', value: false, group: 'surfaces');
    $this->agent->setSetting(key: 'hosted_chat', value: true, group: 'surfaces');

    $this->get($this->chat)
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/Home'));
});
