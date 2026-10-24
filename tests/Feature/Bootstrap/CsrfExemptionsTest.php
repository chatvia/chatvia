<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * The CSRF exemptions bootstrap/app.php sets: the cookie-signed-in hosted
 * chat keeps the check, and only the token-authenticated widget endpoints
 * skip it. Laravel skips the check while running tests, so these requests
 * go through the app's own middleware with that bypass turned off.
 */
beforeEach(function (): void {
    app()->bind(PreventRequestForgery::class, fn (): PreventRequestForgery => new class(app(), app('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
});

it('rejects a cookie-authenticated hosted-chat request without a CSRF token', function (string $method, string $route, array $parameters): void {
    $this->call($method, route($route, ['agentSlug' => 'acme-support', ...$parameters], false))->assertStatus(419);
})->with([
    'stream' => ['POST', 'chat.stream', ['conversationId' => 'c4f5e8a2-6d1b-4f7e-9a3c-2b8d0e6f1a47']],
    'login' => ['POST', 'chat.login', []],
    'delete all conversations' => ['DELETE', 'conversations.delete.all', []],
    'profile' => ['PATCH', 'chat.profile.update', []],
]);

it('keeps the token-authenticated widget endpoints exempt', function (string $uri): void {
    // Past the CSRF check (419), the widget's own token check refuses it.
    $this->post($uri)->assertForbidden();
})->with([
    'widget stream' => fn (): string => route('widget.stream', absolute: false),
]);
