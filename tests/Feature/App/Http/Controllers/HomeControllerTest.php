<?php

use App\Identity\Workspace\Models\User;

/**
 * There is no public landing site: `/` (route `home`) sends guests to the
 * login form and signed-in users to their dashboard, and stops a deactivated
 * user.
 */
it('sends a guest to the login page, a user to the dashboard, and stops a deactivated user', function (): void {
    $this->get('/')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('dashboard'));

    $this->actingAs(User::factory()->create(['deactivated_at' => now()]))
        ->get('/')
        ->assertForbidden();
});
