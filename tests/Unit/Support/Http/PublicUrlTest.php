<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Support\Http\PublicUrl;

/**
 * PublicUrl builds this app's addresses on APP_URL, never on the request's
 * host, and tells when APP_URL is an address only a private network reaches.
 */
it('builds a route on APP_URL, not on the host of the request', function (): void {
    config(['app.url' => 'https://chat.example.com/']);
    app()->instance('request', Request::create('http://localhost:8000/admin'));
    url()->setRequest(app('request'));

    expect(PublicUrl::route('connectors.oauth.callback'))->toBe('https://chat.example.com/connectors/oauth/callback')
        ->and(PublicUrl::route('agents.invitation.social.callback', ['provider' => 'google']))
        ->toBe('https://chat.example.com/auth/google/chat-invitation/callback');
});

it('tells an internal APP_URL from a public one', function (string $appUrl, bool $internal): void {
    config(['app.url' => $appUrl]);

    expect(PublicUrl::isInternal())->toBe($internal);
})->with([
    'localhost' => ['http://localhost:8000', true],
    'a .localhost name' => ['http://chatvia.localhost', true],
    'a container name' => ['http://app:8080', true],
    'Docker host' => ['http://host.docker.internal', true],
    'loopback IP' => ['http://127.0.0.1:8000', true],
    'loopback IPv6' => ['http://[::1]:8000', true],
    'private IP' => ['http://192.168.1.20', true],
    'empty' => ['', true],
    'a public domain' => ['https://chat.example.com', false],
    'a public IP' => ['http://203.0.114.10', false],
    'a .test dev domain' => ['https://chatvia.test', false],
]);
