<?php

use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The "Ask your data" page opens for the workspace owner only, like its
 * stream.
 */
it('opens the page for the owner only', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $employee = User::factory()->employee($owner)->create(['email_verified_at' => now()]);

    $this->actingAs($owner)
        ->get(route('analytics.chat'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('AnalyticsChat'));

    $this->actingAs($employee)->get(route('analytics.chat'))->assertForbidden();
});
