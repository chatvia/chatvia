<?php

use Illuminate\Support\Facades\Route;

/**
 * Public, unauthenticated endpoints that cost money or CPU per call are rate
 * limited with route middleware.
 */
it('throttles guest trial checkout', function (): void {
    // An invalid package fails validation, so no Stripe session is created;
    // the throttle still counts every call.
    foreach (range(1, 10) as $i) {
        $this->post(route('trial.checkout'), ['package' => 'nope'])->assertRedirect();
    }

    $this->post(route('trial.checkout'), ['package' => 'nope'])->assertTooManyRequests();
});

it('rate limits every messaging-channel webhook route', function (string $routeName): void {
    $middleware = Route::getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];

    expect(collect($middleware)->contains(fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'throttle:')))
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
