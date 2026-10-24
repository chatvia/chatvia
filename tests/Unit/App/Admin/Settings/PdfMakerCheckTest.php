<?php

use App\Admin\Settings\PdfMakerCheck;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * PdfMakerCheck makes a one-line PDF with a maker's typed values before they
 * are saved, and says what went wrong: a refused key, a server it cannot
 * reach, or an answer that is not a PDF.
 */
it('makes a test PDF with Cloudflare, with the typed token', function (): void {
    Http::fake(['api.cloudflare.com/*' => Http::response('%PDF-1.7 test', 200)]);

    expect(new PdfMakerCheck()->cloudflare('account-1', 'token-1')->connected)->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cloudflare.com/client/v4/accounts/account-1/browser-rendering/pdf'
        && $request->hasHeader('Authorization', 'Bearer token-1')
        && Str::contains((string) $request['html'], 'Chatvia test page'));
});

it('reports a Cloudflare token without the permission as refused', function (): void {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false], 403)]);

    $result = new PdfMakerCheck()->cloudflare('account-1', 'token-1');

    expect($result->connected)->toBeFalse()
        ->and($result->keyRefused)->toBeTrue()
        ->and($result->message)->toContain('Browser Rendering: Edit');
});

it('passes on what Cloudflare says went wrong', function (): void {
    Http::fake(['api.cloudflare.com/*' => Http::response(['errors' => [['message' => 'Account not found']]], 404)]);

    expect(new PdfMakerCheck()->cloudflare('nope', 'token-1')->message)->toBe('Cloudflare could not make a PDF (error 404): Account not found');
});

it('makes a test PDF with Gotenberg, signing in only when both are given', function (): void {
    Http::fake(['gotenberg.test/*' => Http::response('%PDF-1.7 test', 200)]);

    expect(new PdfMakerCheck()->gotenberg('http://gotenberg.test/', 'pdf', 'secret')->connected)->toBeTrue()
        ->and(new PdfMakerCheck()->gotenberg('http://gotenberg.test', 'pdf', '')->connected)->toBeTrue();

    $sent = Http::recorded()->map(fn (array $pair): Request => $pair[0]);

    expect($sent->map(fn (Request $request): string => $request->url())->all())->toBe(array_fill(0, 2, 'http://gotenberg.test/forms/chromium/convert/html'))
        ->and($sent[0]->hasHeader('Authorization', 'Basic '.base64_encode('pdf:secret')))->toBeTrue()
        ->and($sent[1]->hasHeader('Authorization'))->toBeFalse();
});

it('reports a Gotenberg server it cannot reach, or one that answers with no PDF', function (): void {
    Http::fake(['down.test/*' => fn () => throw new ConnectionException('Connection refused'), 'html.test/*' => Http::response('<html>Login</html>', 200)]);

    expect(new PdfMakerCheck()->gotenberg('http://down.test', null, null)->message)->toBe('Gotenberg could not be reached at http://down.test.')
        ->and(new PdfMakerCheck()->gotenberg('http://html.test', null, null)->message)->toBe('Gotenberg answered, but not with a PDF. Check the address.');
});

it('reports a Gotenberg login it refuses', function (): void {
    Http::fake(['gotenberg.test/*' => Http::response('', 401)]);

    expect(new PdfMakerCheck()->gotenberg('http://gotenberg.test', 'pdf', 'wrong')->keyRefused)->toBeTrue();
});

it('finds the weasyprint program, or says it is missing', function (): void {
    expect(new PdfMakerCheck()->weasyprint('/bin/sh')->connected)->toBeTrue()
        ->and(new PdfMakerCheck()->weasyprint('/nowhere/weasyprint')->message)->toContain('weasyprint was not found');
});
