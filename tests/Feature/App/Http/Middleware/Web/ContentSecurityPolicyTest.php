<?php

use App\Http\Middleware\Web\ContentSecurityPolicy;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The headers the ContentSecurityPolicy middleware sends in each mode the
 * admin picks (`chatvia.security.csp`: off, report-only, enforce; an unknown
 * mode reports only), checked on probe routes that mount it, plus one real
 * page to show it runs in the `web` group. The policy stays minimal (no
 * script-src allowlist: the pages need 'unsafe-inline', which a nonce would
 * disable), and the chat pins img-src against image exfiltration. The
 * hosted chat may be framed anywhere; the dashboard only by itself, in every
 * mode (clickjacking). HSTS goes out on secure production requests to the
 * install's own host in every mode.
 */
beforeEach(function (): void {
    Route::middleware(ContentSecurityPolicy::class)
        ->get('/_csp-probe', fn (): ResponseFactory|Response => response('ok'))
        ->name('csp.probe');

    Route::middleware(ContentSecurityPolicy::class)
        ->get('/_csp-chat-probe', fn (): ResponseFactory|Response => response('ok'))
        ->name('chat.csp-probe');
});

it('enforces the minimal policy, frames the dashboard same-origin only, and pins the chat images while any site may frame it', function (): void {
    config([
        'chatvia.security.csp' => ContentSecurityPolicy::MODE_ENFORCE,
        'filesystems.disks.assets.url' => 'https://cdn-zone.example.net/images',
        'app.asset_url' => 'https://assets.example.net/build',
        'chatvia.security.csp_report_uri' => 'https://reports.example.net/csp',
    ]);

    $dashboard = $this->get('/_csp-probe');
    $chat = $this->get('/_csp-chat-probe');

    $dashboard->assertHeader('Content-Security-Policy')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    expect($dashboard->headers->get('Content-Security-Policy'))
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->toContain("frame-ancestors 'self'")
        ->toContain('report-uri https://reports.example.net/csp')
        ->not->toContain('script-src')
        ->not->toContain('connect-src')
        ->and($chat->headers->get('Content-Security-Policy', ''))->toContain('frame-ancestors *')
        ->and($chat->headers->has('X-Frame-Options'))->toBeFalse()
        ->and(Str::of((string) $chat->headers->get('Content-Security-Policy'))->explode('; ')->first(fn (string $directive): bool => Str::startsWith($directive, 'img-src')))
        ->toContain('https://cdn-zone.example.net')
        ->toContain('https://assets.example.net')
        ->not->toContain('simpleicons')
        ->not->toContain('https://storage');
});

it('only reports the policy, but still frames the dashboard and the sign-in page same-origin only, lets any site frame the chat and sends HSTS', function (string $mode): void {
    config(['chatvia.security.csp' => $mode]);

    $dashboard = $this->get('/_csp-probe');
    $chat = $this->get('/_csp-chat-probe');
    $login = $this->get(route('login'));
    app()['env'] = 'production';
    $secure = $this->get('https://localhost/_csp-probe');

    $dashboard->assertHeader('Content-Security-Policy-Report-Only')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
        ->assertHeaderMissing('Strict-Transport-Security')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $chat->assertHeaderMissing('X-Frame-Options')
        ->assertHeaderMissing('Content-Security-Policy');
    $login->assertOk()
        ->assertHeader('Content-Security-Policy-Report-Only')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $secure->assertHeader('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
    expect($dashboard->headers->get('Content-Security-Policy-Report-Only'))
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'")
        ->and($chat->headers->get('Content-Security-Policy-Report-Only'))->toContain('frame-ancestors *');
})->with([
    'report-only' => ContentSecurityPolicy::MODE_REPORT_ONLY,
    'an unknown mode' => 'enforced-typo',
]);

it('sends only the framing headers and HSTS when switched off, and HSTS only over HTTPS to the install\'s own host', function (): void {
    config(['chatvia.security.csp' => ContentSecurityPolicy::MODE_OFF]);
    app()['env'] = 'production';

    $response = $this->get('https://localhost/_csp-probe');

    $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Strict-Transport-Security')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only')
        ->assertHeaderMissing('X-Content-Type-Options');
    $this->get('http://localhost/_csp-probe')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://help.example.com/_csp-probe')->assertHeaderMissing('Strict-Transport-Security');
});
