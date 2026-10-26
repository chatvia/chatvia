<?php

use Domain\Agent\Models\Agent;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * What the hosted chat's Inertia middleware shares with every chat page:
 * the matched route's name and raw parameters (for useCurrentRoute()), and
 * whether the "Powered by ChatVia.ai" credit shows (`chatvia.powered_by`,
 * Settings → License).
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->agent->update(['is_public' => true]);
});

it('shares the current route with the hosted chat', function (): void {
    $this->get('/chat/'.$this->agent->slug)
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('route.name', 'chat.home')
            ->where('route.params', ['agentSlug' => $this->agent->slug]));
});

it('shares the powered-by switch with the hosted chat', function (bool $shown): void {
    config(['chatvia.powered_by' => $shown]);

    $this->get(route('chat.home', $this->agent->slug))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('poweredBy', $shown));
})->with(['credit shown' => true, 'credit hidden' => false]);
