<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Public, unauthenticated endpoints that cost money or CPU per call are rate
 * limited with route middleware.
 */
// Creating workspaces past the first one needs a license key; the free-use
// limit itself is covered in WorkspaceLimitTest.
beforeEach(fn () => withLicense());

it('throttles guest sign-up', function (): void {
    // Invalid input fails validation, so no account is created; the throttle
    // still counts every call.
    foreach (range(1, 6) as $i) {
        $this->post(route('register.store'), ['email' => 'not-an-email'])->assertRedirect();
    }

    $this->post(route('register.store'), ['email' => 'not-an-email'])->assertTooManyRequests();
});

it('rate limits every messaging-channel webhook route', function (string $routeName): void {
    $middleware = Route::getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];

    expect(collect($middleware)->contains(fn (mixed $m): bool => ! $m instanceof Closure && Str::startsWith($m, 'throttle:')))
        ->toBeTrue();
})->with([
    'telegram.webhook',
    'slack.webhook',
    'msteams.webhook',
    'messenger.webhook',
    'messenger.webhook.verify',
    'whatsapp.webhook',
    'whatsapp.webhook.verify',
    'instagram.webhook',
    'instagram.webhook.verify',
]);
