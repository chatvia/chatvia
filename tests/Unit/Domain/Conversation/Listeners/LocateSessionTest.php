<?php

use Domain\Conversation\Events\SessionLocationUnknown;
use Domain\Conversation\Listeners\LocateSession;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\Http;
use Support\Localization\Country\Models\Country;

/**
 * A new session's location: the country comes from the request (Cloudflare's
 * country header), never from geo-locating the stored address, which may be
 * Cloudflare's edge. A disagreeing address's city is dropped, and a known
 * country is never overwritten.
 */
beforeEach(function (): void {
    config([
        'services.ip2location.endpoint' => 'https://ip2location.test/',
        'services.ip2location.key' => 'test-key',
    ]);

    foreach (['iq' => 'Iraq', 'bg' => 'Bulgaria'] as $code => $name) {
        Country::firstOrCreate(['code' => $code], ['name' => $name]);
    }
});

function lcsFakeIpLocation(array $body): void
{
    Http::fake(['ip2location.test*' => Http::response($body)]);
}

function lcsLocate(Session $session, ?string $countryCode): void
{
    app(LocateSession::class)->handle(new SessionLocationUnknown($session, $countryCode));
}

it('stamps the request-resolved country and ignores a disagreeing IP geo', function (): void {
    lcsFakeIpLocation([
        'ip' => '203.0.113.10',
        'country_code' => 'BG',
        'city_name' => 'Sofia',
        'region_name' => 'Sofia-grad',
        'is_proxy' => false,
    ]);

    $session = Session::factory()->create(['ip_address' => '203.0.113.10', 'country_id' => null, 'city' => null]);

    lcsLocate($session, 'iq');

    $session->refresh();
    expect($session->country_id)->toBe(Country::where('code', 'iq')->value('id'))
        ->and($session->city)->toBeNull()
        ->and($session->region)->toBeNull()
        ->and($session->is_proxy)->toBeFalse();
});

it('never stamps a country from IP geo, and never overwrites a resolved one', function (): void {
    lcsFakeIpLocation(['ip' => '203.0.113.10', 'country_code' => 'BG', 'city_name' => 'Sofia']);
    $bg = Country::where('code', 'bg')->value('id');
    $unknown = Session::factory()->create(['ip_address' => '203.0.113.10', 'country_id' => null, 'city' => null]);
    $resolved = Session::factory()->create(['ip_address' => '203.0.113.10', 'country_id' => $bg]);

    lcsLocate($unknown, null);
    lcsLocate($resolved, 'iq');

    expect($unknown->refresh()->country_id)->toBeNull()
        ->and($unknown->city)->toBeNull()
        ->and($resolved->refresh()->country_id)->toBe($bg);
});
