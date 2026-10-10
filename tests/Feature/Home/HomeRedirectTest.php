<?php

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * There is no public landing site: `/` (route `home`) sends guests to the
 * login form and signed-in users to their dashboard, and the old marketing
 * URLs are gone.
 */
afterEach(function (): void {
    putenv('ROUTING_LOCALE=en');
    $_ENV['ROUTING_LOCALE'] = $_SERVER['ROUTING_LOCALE'] = 'en';
});

it('sends a guest from / to the login page', function (): void {
    expect(Route::has('home'))->toBeTrue();

    $this->get('/en')->assertRedirect(route('login'));
});

it('sends a guest from an un-prefixed / to the login page', function (): void {
    // The harness pins routes to ROUTING_LOCALE (en); clear it and reboot to
    // get the un-prefixed registration a real first visit gets.
    putenv('ROUTING_LOCALE');
    unset($_ENV['ROUTING_LOCALE'], $_SERVER['ROUTING_LOCALE']);
    $this->refreshApplication();

    $this->get('/')->assertRedirect(route('login'));
});

it('sends a signed-in workspace user from / to the dashboard', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/en')
        ->assertRedirect(route('dashboard'));
});

it('does not loop a signed-in chatter between / and the dashboard', function (): void {
    $this->actingAs(User::factory()->create(['type' => UserType::Chatter]))
        ->get('/en')
        ->assertForbidden();
});

it('serves no marketing page', function (string $url): void {
    $this->get($url)->assertNotFound();
})->with([
    '/en/pricing',
    '/en/blog',
    '/en/changelog',
    '/en/mission',
    '/en/security',
    '/en/privacy-policy',
    '/en/terms-of-service',
    '/en/legal/dpa',
    '/en/contact',
    '/legal/dpa/download',
]);
