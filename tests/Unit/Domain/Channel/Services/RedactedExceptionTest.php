<?php

use Domain\Channel\Services\RedactedException;
use Illuminate\Http\Client\ConnectionException;

/**
 * Channel API exceptions carry the request URL in their message. Meta puts
 * the app secret and tokens in the query string, Telegram puts the bot token
 * in the path, so the logged message must have them removed.
 */
it('removes a Telegram bot token from the request path', function (): void {
    $e = new RuntimeException('cURL error 28: timed out for https://api.telegram.org/bot123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw/sendMessage');

    $context = RedactedException::context($e);

    expect($context['message'])->not->toContain('AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw')
        ->and($context['message'])->toContain('/bot[redacted]/sendMessage')
        ->and($context['exception'])->toBe(RuntimeException::class);
});

it('removes the query string of a Meta Graph URL', function (): void {
    $e = new ConnectionException('cURL error 7: failed for https://graph.facebook.com/v25.0/oauth/access_token?client_id=1&client_secret=SUPER-SECRET&code=abc');

    $context = RedactedException::context($e);

    expect($context['message'])->not->toContain('SUPER-SECRET')
        ->and($context['message'])->not->toContain('code=abc')
        ->and($context['message'])->toContain('https://graph.facebook.com/v25.0/oauth/access_token?[redacted]');
});

it('removes bearer tokens and form-encoded secrets', function (): void {
    $redacted = RedactedException::redact('Authorization: Bearer EAAGabc.def-123 body: access_token=EAAG999&client_secret=xyz');

    expect($redacted)->not->toContain('EAAGabc')
        ->and($redacted)->not->toContain('EAAG999')
        ->and($redacted)->not->toContain('xyz')
        ->and($redacted)->toContain('Bearer [redacted]');
});

it('caps very long messages', function (): void {
    expect(mb_strlen(RedactedException::redact(str_repeat('a', 5000))))->toBeLessThanOrEqual(301);
});
