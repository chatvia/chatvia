<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Both Inertia middlewares share the matched route's name and raw parameters,
 * which the frontend's useCurrentRoute() reads for active-state checks (URLs
 * themselves come from Wayfinder). Parameters are the raw URL segments, never
 * serialized models.
 */
it('shares the current route with dashboard pages', function (): void {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('agents.forms.index', $agent))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('route.name', 'agents.forms.index')
            ->where('route.params', ['agent' => $agent->external_id]));
});

it('shares the current route with the hosted chat', function (): void {
    $agent = Agent::factory()->create();
    $agent->update(['is_public' => true]);

    $this->get('https://'.$agent->sub_domain.'.chatvia.test/chat')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('route.name', 'chat.home')
            ->where('route.params', []));
});
