<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * There is no public landing site: `/` (route `home`) sends guests to the
 * login form and signed-in users to their dashboard, and the old marketing
 * URLs are gone.
 */
it('sends a guest from / to the login page', function (): void {
    expect(Route::has('home'))->toBeTrue();

    $this->get('/')->assertRedirect(route('login'));
});

it('sends a signed-in workspace user from / to the dashboard', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

it('sends a chatter signed in to a chat from / to the login page', function (): void {
    // A chatter's sign-in lives on the chat's own guard; `/` reads the
    // dashboard's, where chatters never exist.
    $this->actingAs(Chatter::factory()->create(), 'chat')
        ->get('/')
        ->assertRedirect(route('login'));
});

it('stops a deactivated user at / instead of sending them to the dashboard', function (): void {
    $this->actingAs(User::factory()->create(['deactivated_at' => now()]))
        ->get('/')
        ->assertForbidden();
});
