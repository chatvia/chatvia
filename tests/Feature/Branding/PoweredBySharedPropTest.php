<?php

use Domain\Agent\Models\Agent;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The hosted chat shares whether the "Powered by ChatVia.ai" credit shows
 * (`chatvia.powered_by`, Settings → Branding).
 */
it('shares the powered-by switch with the hosted chat', function (bool $shown): void {
    config(['chatvia.powered_by' => $shown]);
    $agent = Agent::factory()->create();
    $agent->update(['is_public' => true]);

    $this->get(route('chat.home', $agent->slug))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('poweredBy', $shown));
})->with(['credit shown' => true, 'credit hidden' => false]);
