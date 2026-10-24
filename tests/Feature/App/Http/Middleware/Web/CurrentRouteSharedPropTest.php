<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The dashboard's Inertia middleware shares the matched route's name and raw
 * parameters, which the frontend's useCurrentRoute() reads for active-state
 * checks (URLs themselves come from Wayfinder). Parameters are the raw URL
 * segments, never serialized models. The hosted chat's middleware does the
 * same (ChatHandleInertiaRequestsTest).
 */
it('shares the current route with dashboard pages', function (): void {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['workspace_id' => $user->workspace()->id]);

    $this->actingAs($user)
        ->get(route('agents.forms.index', $agent))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('route.name', 'agents.forms.index')
            ->where('route.params', ['agent' => $agent->external_id]));
});
