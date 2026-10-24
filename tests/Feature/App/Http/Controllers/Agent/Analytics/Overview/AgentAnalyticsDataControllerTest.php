<?php

use App\Identity\Workspace\Models\User;

/**
 * The agent Analytics overview's numbers for a date range; a range that ends
 * before it starts is refused.
 */
it('rejects an end date before the start date', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    $this->actingAs($owner)
        ->getJson(route('agents.analytics.data', [$agent, 'start_date' => '2026-06-10', 'end_date' => '2026-06-01']))
        ->assertJsonValidationErrors('end_date');
});
