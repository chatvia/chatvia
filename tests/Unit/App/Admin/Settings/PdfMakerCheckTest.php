<?php

use App\Admin\Settings\PdfMakerCheck;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * PdfMakerCheck makes a one-line PDF with a maker's typed values before they
 * are saved, and says what went wrong: a server it cannot reach, or an
 * answer that is not a PDF.
 */
it('makes a test PDF with Cloudflare, with the typed token', function (): void {
    Http::fake(['api.cloudflare.com/*' => Http::response('%PDF-1.7 test', 200)]);

    expect(new PdfMakerCheck()->cloudflare('account-1', 'token-1')->connected)->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cloudflare.com/client/v4/accounts/account-1/browser-rendering/pdf'
        && $request->hasHeader('Authorization', 'Bearer token-1')
        && Str::contains((string) $request['html'], 'Chatvia test page'));
});

it('reports a Gotenberg server it cannot reach, or one that answers with no PDF', function (): void {
    Http::fake(['down.test/*' => fn () => throw new ConnectionException('Connection refused'), 'html.test/*' => Http::response('<html>Login</html>', 200)]);

    expect(new PdfMakerCheck()->gotenberg('http://down.test', null, null)->message)->toBe('Gotenberg could not be reached at http://down.test.')
        ->and(new PdfMakerCheck()->gotenberg('http://html.test', null, null)->message)->toBe('Gotenberg answered, but not with a PDF. Check the address.');
});
