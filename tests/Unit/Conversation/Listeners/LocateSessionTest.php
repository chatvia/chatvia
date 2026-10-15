<?php

use Domain\Conversation\Events\SessionLocationUnknown;
use Domain\Conversation\Listeners\LocateSession;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\Http;
use Support\Localization\Country\Models\Country;

beforeEach(function (): void {
    config([
        'services.ip2location.endpoint' => 'https://ip2location.test/',
        'services.ip2location.key' => 'test-key',
    ]);

    // Seed-independent: ensure the codes the tests assert against exist.
    foreach (['iq' => 'Iraq', 'us' => 'United States', 'bg' => 'Bulgaria'] as $code => $name) {
        Country::firstOrCreate(['code' => $code], ['name' => $name]);
    }
});

function fakeIpLocation(array $body): void
{
    Http::fake(['ip2location.test*' => Http::response($body)]);
}

function locateSession(Session $session, ?string $countryCode): void
{
    app(LocateSession::class)->handle(new SessionLocationUnknown($session, $countryCode));
}

it('stamps the request-resolved country and ignores a disagreeing IP geo', function (): void {
    // Cloudflare says Iraq; the stored IP (the CF edge PoP) geo-locates to
    // Bulgaria. The country must follow Cloudflare, and Bulgaria's city must be
    // dropped rather than stamped against an Iraqi session.
    fakeIpLocation([
        'ip' => '203.0.113.10',
        'country_code' => 'BG',
        'city_name' => 'Sofia',
        'region_name' => 'Sofia-grad',
        'is_proxy' => false,
    ]);

    $session = Session::factory()->create(['ip_address' => '203.0.113.10', 'country_id' => null, 'city' => null]);

    locateSession($session, 'iq');

    $session->refresh();
    expect($session->country_id)->toBe(Country::where('code', 'iq')->value('id'))
        ->and($session->city)->toBeNull()
        ->and($session->region)->toBeNull()
        ->and($session->is_proxy)->toBeFalse();
});

it('keeps IP-derived city/region when the IP geo agrees with the country', function (): void {
    fakeIpLocation([
        'ip' => '198.51.100.7',
        'country_code' => 'US',
        'city_name' => 'Seattle',
        'region_name' => 'Washington',
    ]);

    $session = Session::factory()->create(['ip_address' => '198.51.100.7', 'country_id' => null, 'city' => null]);

    locateSession($session, 'us');

    $session->refresh();
    expect($session->country_id)->toBe(Country::where('code', 'us')->value('id'))
        ->and($session->city)->toBe('Seattle')
        ->and($session->region)->toBe('Washington');
});

it('never stamps a country from IP geo when no Cloudflare country is supplied', function (): void {
    // No trusted request-time country. The stored IP may be the Cloudflare edge
    // PoP, so geo-locating it for country would stamp the edge's country
    // instead of the visitor's. Leave country_id null: Request::country()
    // already geo-locates the real visitor at request time when there is no
    // Cloudflare in front.
    fakeIpLocation(['ip' => '203.0.113.10', 'country_code' => 'BG', 'city_name' => 'Sofia']);

    $session = Session::factory()->create(['ip_address' => '203.0.113.10', 'country_id' => null, 'city' => null]);

    locateSession($session, null);

    $session->refresh();
    expect($session->country_id)->toBeNull()
        ->and($session->city)->toBeNull();
});

it('never overwrites an already-resolved country', function (): void {
    $bg = Country::where('code', 'bg')->value('id');
    $session = Session::factory()->create(['ip_address' => '203.0.113.10', 'country_id' => $bg]);

    locateSession($session, 'iq');

    expect($session->refresh()->country_id)->toBe($bg);
});
