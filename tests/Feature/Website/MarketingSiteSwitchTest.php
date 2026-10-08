<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Route;
use Support\Enums\User\UserType;

/**
 * The marketing routes are registered at boot from
 * `chatvia.brand.marketing_site`, so each case reboots the app with the env
 * var set. Users are built with make() (never saved): the rebooted app has a
 * fresh DB connection outside the test transaction.
 */
function bootWithMarketingSite(bool $enabled, bool $unprefixed = false): void
{
    $value = $enabled ? 'true' : 'false';
    putenv("MARKETING_SITE_ENABLED={$value}");
    $_ENV['MARKETING_SITE_ENABLED'] = $_SERVER['MARKETING_SITE_ENABLED'] = $value;

    if ($unprefixed) {
        // The harness pins routes to ROUTING_LOCALE (en); clear it to get the
        // un-prefixed registration a real first visit gets.
        putenv('ROUTING_LOCALE');
        unset($_ENV['ROUTING_LOCALE'], $_SERVER['ROUTING_LOCALE']);
    }

    test()->refreshApplication();
}

afterEach(function (): void {
    putenv('MARKETING_SITE_ENABLED');
    unset($_ENV['MARKETING_SITE_ENABLED'], $_SERVER['MARKETING_SITE_ENABLED']);

    putenv('ROUTING_LOCALE=en');
    $_ENV['ROUTING_LOCALE'] = $_SERVER['ROUTING_LOCALE'] = 'en';
});

$marketingRoutes = [
    'pricing', 'mission', 'security', 'privacy', 'terms', 'refund', 'dpa',
    'dpa.download', 'subprocessors', 'changelog', 'blog', 'blog.post',
    'contact', 'contact.submit', 'use-cases', 'free-tools.chat-with-pdf',
];

it('serves the marketing site by default', function () use ($marketingRoutes): void {
    expect(config('chatvia.brand.marketing_site'))->toBeTrue();

    foreach ($marketingRoutes as $name) {
        expect(Route::has($name))->toBeTrue("route [{$name}] should be registered");
    }

    $this->get('/en')->assertOk();
    $this->get('/en/pricing')->assertOk();
});

it('registers no marketing route when the marketing site is off', function () use ($marketingRoutes): void {
    bootWithMarketingSite(false);

    expect(config('chatvia.brand.marketing_site'))->toBeFalse()
        // The app still redirects to `home`, so the name must keep resolving.
        ->and(Route::has('home'))->toBeTrue();

    foreach ($marketingRoutes as $name) {
        expect(Route::has($name))->toBeFalse("route [{$name}] should not be registered");
    }

    $this->get('/en/pricing')->assertNotFound();
    $this->get('/en/privacy-policy')->assertNotFound();
    $this->get('/en/legal/dpa')->assertNotFound();
    $this->get('/en/contact')->assertNotFound();
    $this->get('/legal/dpa/download?company_name=Acme')->assertNotFound();
});

it('sends a guest from / to the login page when the marketing site is off', function (): void {
    bootWithMarketingSite(false);

    $this->get('/en')->assertRedirect(route('login'));
});

it('sends an un-prefixed / to the login page when the marketing site is off', function (): void {
    bootWithMarketingSite(false, unprefixed: true);

    $this->get('/')->assertRedirect(route('login'));
});

it('sends a signed-in workspace user from / to the dashboard when the marketing site is off', function (): void {
    bootWithMarketingSite(false);

    $this->actingAs(User::factory()->make(['id' => 999_999_001]))
        ->get('/en')
        ->assertRedirect(route('dashboard'));
});

it('does not loop a signed-in chatter between / and the dashboard', function (): void {
    bootWithMarketingSite(false);

    $this->actingAs(User::factory()->make(['id' => 999_999_002, 'type' => UserType::Chatter]))
        ->get('/en')
        ->assertForbidden();
});
