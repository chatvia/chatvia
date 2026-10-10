<?php

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;

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

it('rejects a cookie-authenticated hosted-chat POST without a CSRF token', function (string $uri): void {
    expect(fn () => csrfMiddleware()->handle(csrfRequest($uri), fn (): Response => new Response('ok')))
        ->toThrow(TokenMismatchException::class);
})->with([
    'stream' => '/chat/stream/abc',
    'login' => '/chat/login',
    'delete all conversations' => '/chat/conversations',
    'profile' => '/chat/profile',
]);

it('accepts a hosted-chat POST that echoes the XSRF-TOKEN cookie back', function (): void {
    $request = csrfRequest('/chat/stream/abc');
    // What the browser sends: the encrypted XSRF-TOKEN cookie value.
    $xsrf = app('encrypter')->encrypt(
        CookieValuePrefix::create('XSRF-TOKEN', app('encrypter')->getKey()).$request->session()->token(),
        PreventRequestForgery::serialized(),
    );
    $request->headers->set('X-XSRF-TOKEN', $xsrf);

    $response = csrfMiddleware()->handle($request, fn (): Response => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('keeps the token-authenticated widget endpoints exempt', function (string $uri): void {
    $response = csrfMiddleware()->handle(csrfRequest($uri), fn (): Response => new Response('ok'));

    expect($response->getContent())->toBe('ok');
})->with([
    'widget stream' => '/widget/stream',
]);
