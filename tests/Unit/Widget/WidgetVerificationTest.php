<?php

use Domain\Agent\Models\Agent;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\Request;

/**
 * The embed-origin allow-list is the customer's own `sites` plus install-wide
 * hosts from config: the APP_URL host (dashboard playground, hosted chat) and
 * any extra WIDGET_ALWAYS_ALLOWED_ORIGINS. No vendor domain may be built in,
 * or every self-hosted install would accept embeds from that domain.
 */

/**
 * Evaluate the shipped config file under the given env values.
 *
 * @param  array<string, string>  $env
 * @return list<string>
 */
function alwaysAllowedOriginsFor(array $env): array
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
function originRequest(string $origin): Request
{
    $request = Request::create('/widget/verify', 'GET');
    $request->headers->set('Origin', $origin);

    return $request;
}

function agentWithSites(array $sites): Agent
{
    $agent = Agent::factory()->create();
    $agent->setSetting('sites', $sites, 'widget');

    return $agent->fresh();
}

it('trusts no outside domain when no always-allowed origins are configured', function (string $origin): void {
    config(['chatvia.widget.always_allowed_origins' => []]);
    $agent = agentWithSites(['customer.example']);

    expect(app(WidgetVerification::class)->verifyByAgentExternalId($agent->external_id, originRequest($origin)))
        ->toBeFalse();
})->with([
    'https://chatvia.ai',
    'https://chatvia.test',
    'http://localhost',
]);

it('allows the agent\'s own sites', function (): void {
    config(['chatvia.widget.always_allowed_origins' => []]);
    $agent = agentWithSites(['customer.example']);

    expect(app(WidgetVerification::class)->verifyByAgentExternalId($agent->external_id, originRequest('https://www.customer.example')))
        ->toBeTrue();
});

it('allows the configured install-wide origins for every agent', function (): void {
    config(['chatvia.widget.always_allowed_origins' => ['chatvia.ai']]);
    $agent = agentWithSites([]);

    $verification = app(WidgetVerification::class);

    expect($verification->verifyByAgentExternalId($agent->external_id, originRequest('https://chatvia.ai')))->toBeTrue()
        ->and($verification->verifyByAgentExternalId($agent->external_id, originRequest('https://evil.example')))->toBeFalse();
});

it('allows localhost only in the local environment', function (): void {
    config(['chatvia.widget.always_allowed_origins' => []]);
    app()['env'] = 'local';
    $agent = agentWithSites([]);

    expect(app(WidgetVerification::class)->verifyByAgentExternalId($agent->external_id, originRequest('http://localhost:5173')))
        ->toBeTrue();
});

it('defaults to the install\'s own APP_URL host and nothing else', function (): void {
    expect(alwaysAllowedOriginsFor(['APP_URL' => 'https://chat.acme.example', 'WIDGET_ALWAYS_ALLOWED_ORIGINS' => '']))
        ->toBe(['chat.acme.example']);
});

it('adds the extra hosts from WIDGET_ALWAYS_ALLOWED_ORIGINS to the APP_URL host', function (): void {
    expect(alwaysAllowedOriginsFor(['APP_URL' => 'https://chatvia.ai', 'WIDGET_ALWAYS_ALLOWED_ORIGINS' => ' Partner.example , ,chatvia.ai']))
        ->toBe(['chatvia.ai', 'partner.example']);
});

it('lets the widget load on the APP_URL host with the default config', function (): void {
    $agent = agentWithSites([]);
    // No config override: this is the list the app booted with.
    $appHost = (string) parse_url((string) env('APP_URL'), PHP_URL_HOST);

    $verification = app(WidgetVerification::class);

    expect($verification->verifyByAgentExternalId($agent->external_id, originRequest('https://'.$appHost)))->toBeTrue()
        ->and($verification->verifyByAgentExternalId($agent->external_id, originRequest('https://www.'.$appHost)))->toBeTrue()
        ->and($verification->verifyByAgentExternalId($agent->external_id, originRequest('https://chatvia.ai')))->toBe($appHost === 'chatvia.ai');
});
