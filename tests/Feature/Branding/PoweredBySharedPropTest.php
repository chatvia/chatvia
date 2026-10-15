<?php

use Domain\Agent\Models\Agent;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The hosted chat shares whether the "Powered by Chatvia" credit shows
 * (`chatvia.powered_by`, Settings → Branding).
 */
it('shares the powered-by switch with the hosted chat', function (): void {
    config(['chatvia.powered_by' => true]);
    $agent = Agent::factory()->create();
    $agent->update(['is_public' => true]);

    $this->get('/chat/'.$agent->slug)
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('poweredBy', true));
});
