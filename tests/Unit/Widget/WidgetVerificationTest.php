<?php

use Domain\Agent\Models\Agent;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\Request;

/**
 * The embed-origin allow-list is the customer's own `sites` plus install-wide
 * hosts from config: the APP_URL host (dashboard playground, hosted chat) and
 * the extra hosts set in the admin panel. No vendor domain may be built in,
 * or every self-hosted install would accept embeds from that domain.
 */

/**
 * Evaluate the shipped config file under the given env values.
 *
 * @param  array<string, string>  $env
 * @return list<string>
 */
function wvAlwaysAllowedOriginsFor(array $env): array
{
    $saved = [];
    foreach ($env as $key => $value) {
        $saved[$key] = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null];
        $_SERVER[$key] = $_ENV[$key] = $value;
    }

    try {
        return (require config_path('chatvia.php'))['widget']['always_allowed_origins'];
    } finally {
        foreach ($saved as $key => [$server, $envValue]) {
            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }

            if ($envValue === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $envValue;
            }
        }
    }
}

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

it('trusts no outside domain when no always-allowed origins are configured', function (string $origin): void {
    config(['chatvia.widget.always_allowed_origins' => []]);
    $agent = wvAgentWithSites(['customer.example']);

    expect(app(WidgetVerification::class)->isRequestFromAllowedSite($agent, wvOriginRequest($origin)))
        ->toBeFalse();
})->with([
    'https://chatvia.ai',
    'https://chatvia.test',
    'http://localhost',
]);

it('allows the agent\'s own sites', function (): void {
    config(['chatvia.widget.always_allowed_origins' => []]);
    $agent = wvAgentWithSites(['customer.example']);

    expect(app(WidgetVerification::class)->isRequestFromAllowedSite($agent, wvOriginRequest('https://www.customer.example')))
        ->toBeTrue();
});

it('allows the configured install-wide origins for every agent', function (): void {
    config(['chatvia.widget.always_allowed_origins' => ['chatvia.ai']]);
    $agent = wvAgentWithSites([]);

    $verification = app(WidgetVerification::class);

    expect($verification->isRequestFromAllowedSite($agent, wvOriginRequest('https://chatvia.ai')))->toBeTrue()
        ->and($verification->isRequestFromAllowedSite($agent, wvOriginRequest('https://evil.example')))->toBeFalse();
});

it('allows localhost only in the local environment', function (): void {
    config(['chatvia.widget.always_allowed_origins' => []]);
    app()['env'] = 'local';
    $agent = wvAgentWithSites([]);

    expect(app(WidgetVerification::class)->isRequestFromAllowedSite($agent, wvOriginRequest('http://localhost:5173')))
        ->toBeTrue();
});

it('defaults to the install\'s own APP_URL host and nothing else', function (): void {
    expect(wvAlwaysAllowedOriginsFor(['APP_URL' => 'https://chat.acme.example']))
        ->toBe(['chat.acme.example']);
});

it('adds the extra hosts set in the admin panel to the APP_URL host', function (): void {
    config([
        'chatvia.widget.always_allowed_origins' => ['chatvia.ai'],
        'chatvia.widget.extra_allowed_origins' => ' Partner.example , ,chatvia.ai',
    ]);
    $agent = wvAgentWithSites([]);

    $verification = app(WidgetVerification::class);

    expect($verification->isRequestFromAllowedSite($agent, wvOriginRequest('https://chatvia.ai')))->toBeTrue()
        ->and($verification->isRequestFromAllowedSite($agent, wvOriginRequest('https://partner.example')))->toBeTrue()
        ->and($verification->isRequestFromAllowedSite($agent, wvOriginRequest('https://evil.example')))->toBeFalse();
});

it('lets the widget load on the APP_URL host with the default config', function (): void {
    $agent = wvAgentWithSites([]);
    // No config override: this is the list the app booted with.
    $appHost = (string) parse_url((string) env('APP_URL'), PHP_URL_HOST);

    $verification = app(WidgetVerification::class);

    expect($verification->isRequestFromAllowedSite($agent, wvOriginRequest('https://'.$appHost)))->toBeTrue()
        ->and($verification->isRequestFromAllowedSite($agent, wvOriginRequest('https://www.'.$appHost)))->toBeTrue()
        ->and($verification->isRequestFromAllowedSite($agent, wvOriginRequest('https://chatvia.ai')))->toBe($appHost === 'chatvia.ai');
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

it('matches a wildcard site on the bare domain and on every subdomain', function (string $origin, bool $allowed): void {
    config(['chatvia.widget.always_allowed_origins' => []]);
    $agent = wvAgentWithSites(['*.customer.example']);

    expect(app(WidgetVerification::class)->isRequestFromAllowedSite($agent, wvOriginRequest($origin)))->toBe($allowed);
})->with([
    'bare domain' => ['https://customer.example', true],
    'subdomain' => ['https://help.customer.example', true],
    'deep subdomain' => ['https://a.b.customer.example', true],
    'look-alike' => ['https://evilcustomer.example', false],
]);
