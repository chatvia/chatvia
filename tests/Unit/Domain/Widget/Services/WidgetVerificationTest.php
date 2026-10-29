<?php

use Domain\Agent\Models\Agent;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\Request;

/**
 * The embed-origin allow-list is the customer's own `sites` plus install-wide
 * hosts from config: the APP_URL host (dashboard playground, hosted chat) and
 * the extra hosts set in the admin panel. No vendor domain may be built in,
 * or every self-hosted install would accept embeds from that domain. The
 * origin is read from Origin, then Referer, then the request host.
 */
function wvOriginRequest(string $origin): Request
{
    $request = Request::create('/widget/verify', 'GET');
    $request->headers->set('Origin', $origin);

    return $request;
}

function wvAgentWithSites(array $sites): Agent
{
    $agent = Agent::factory()->create();
    $agent->setSetting('sites', $sites, 'widget');

    return $agent->fresh();
}

it('allows only the agent\'s own sites, wildcards included, and trusts no outside domain', function (string $origin, bool $allowed): void {
    config(['chatvia.widget.always_allowed_origins' => []]);
    $agent = wvAgentWithSites(['customer.example', '*.wild.example']);

    expect(app(WidgetVerification::class)->isRequestFromAllowedSite($agent, wvOriginRequest($origin)))->toBe($allowed);
})->with([
    'the agent\'s own site' => ['https://www.customer.example', true],
    'a vendor domain' => ['https://chatvia.ai', false],
    'a test domain' => ['https://chatvia.test', false],
    'localhost outside local' => ['http://localhost', false],
    'a wildcard site, bare domain' => ['https://wild.example', true],
    'a wildcard site, subdomain' => ['https://help.wild.example', true],
    'a wildcard site, deep subdomain' => ['https://a.b.wild.example', true],
    'a look-alike of a wildcard site' => ['https://evilwild.example', false],
]);

it('allows the install-wide and admin-set hosts for every agent, and localhost only in the local environment', function (): void {
    config([
        'chatvia.widget.always_allowed_origins' => ['chatvia.ai'],
        'chatvia.widget.extra_allowed_origins' => ' Partner.example , ,chatvia.ai',
    ]);
    $agent = wvAgentWithSites([]);
    $verification = app(WidgetVerification::class);
    $outsideLocal = $verification->isRequestFromAllowedSite($agent, wvOriginRequest('http://localhost:5173'));
    app()['env'] = 'local';

    expect($verification->isRequestFromAllowedSite($agent, wvOriginRequest('https://chatvia.ai')))->toBeTrue()
        ->and($verification->isRequestFromAllowedSite($agent, wvOriginRequest('https://partner.example')))->toBeTrue()
        ->and($verification->isRequestFromAllowedSite($agent, wvOriginRequest('https://evil.example')))->toBeFalse()
        ->and($outsideLocal)->toBeFalse()
        ->and($verification->isRequestFromAllowedSite($agent, wvOriginRequest('http://localhost:5173')))->toBeTrue();
});

it('falls back from Origin to Referer to the request host', function (): void {
    config(['chatvia.widget.always_allowed_origins' => []]);
    $agent = wvAgentWithSites(['customer.example']);
    $verification = app(WidgetVerification::class);

    $fromReferer = Request::create('/widget/verify', 'GET');
    $fromReferer->headers->set('Referer', 'https://customer.example/pricing');

    $fromHost = Request::create('https://customer.example/widget/verify', 'GET');

    $originWins = wvOriginRequest('https://evil.example');
    $originWins->headers->set('Referer', 'https://customer.example/');

    expect($verification->isRequestFromAllowedSite($agent, $fromReferer))->toBeTrue()
        ->and($verification->isRequestFromAllowedSite($agent, $fromHost))->toBeTrue()
        ->and($verification->isRequestFromAllowedSite($agent, $originWins))->toBeFalse();
});
