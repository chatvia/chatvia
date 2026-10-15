<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Str;

/**
 * Laravel skips the CSRF check while running tests, so these tests drive the
 * real middleware (with the exemption list bootstrap/app.php configures) with
 * that test bypass turned off.
 */
function csrfMiddleware(): PreventRequestForgery
{
    return new class(app(), app('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    };
}

/**
 * @param  array<string, string>  $headers
 */
function csrfRequest(string $uri, array $headers = []): Request
{
    $request = Request::create($uri, 'POST', server: collect($headers)
        ->mapWithKeys(fn (string $value, string $name): array => ['HTTP_'.strtoupper(str_replace('-', '_', $name)) => $value])
        ->all());

    $session = app('session.store');
    $session->start();
    $request->setLaravelSession($session);

    return $request;
}

it('rejects a cookie-authenticated hosted-chat request without a CSRF token', function (string $uri): void {
    expect(fn (): Response => csrfMiddleware()->handle(csrfRequest($uri), fn (): Response => new Response('ok')))
        ->toThrow(TokenMismatchException::class);
})->with([
    'stream' => fn (): string => route('chat.stream', ['agentSlug' => 'acme-support', 'conversationId' => (string) Str::uuid()], false),
    'login' => fn (): string => route('chat.login', ['agentSlug' => 'acme-support'], false),
    'delete all conversations' => fn (): string => route('conversations.delete.all', ['agentSlug' => 'acme-support'], false),
    'profile' => fn (): string => route('chat.profile.update', ['agentSlug' => 'acme-support'], false),
]);

it('keeps the token-authenticated widget endpoints exempt', function (string $uri): void {
    $response = csrfMiddleware()->handle(csrfRequest($uri), fn (): Response => new Response('ok'));

    expect($response->getContent())->toBe('ok');
})->with([
    'widget stream' => fn (): string => route('widget.stream', absolute: false),
]);
