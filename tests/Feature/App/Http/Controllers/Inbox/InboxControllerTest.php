<?php

use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;

/** The Inbox page of the dashboard; its list loads from the JSON endpoint. */
it('renders the Inbox page', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('inbox/Index'));
});
