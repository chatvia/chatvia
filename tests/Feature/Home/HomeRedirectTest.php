<?php

use App\Identity\Workspace\Enums\UserType;
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

it('does not loop a signed-in chatter between / and the dashboard', function (): void {
    $this->actingAs(User::factory()->create(['type' => UserType::Chatter]))
        ->get('/')
        ->assertForbidden();
});

it('serves no marketing page', function (string $url): void {
    $this->get($url)->assertNotFound();
})->with([
    '/pricing',
    '/blog',
    '/changelog',
    '/mission',
    '/security',
    '/privacy-policy',
    '/terms-of-service',
    '/legal/dpa',
    '/contact',
    '/legal/dpa/download',
]);
