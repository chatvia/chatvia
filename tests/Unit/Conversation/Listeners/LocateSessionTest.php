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

function locate(Session $session, ?string $countryCode): void
{
    (new LocateSession)->handle(new SessionLocationUnknown($session, $countryCode));
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

    locate($session, 'iq');

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

    locate($session, 'us');

    $session->refresh();
    expect($session->country_id)->toBe(Country::where('code', 'us')->value('id'))
        ->and($session->city)->toBe('Seattle')
        ->and($session->region)->toBe('Washington');
});

it('falls back to the IP geo country when no Cloudflare country is supplied', function (): void {
    // Dev / no-CF: the connecting IP is the real visitor, so geo it.
    fakeIpLocation(['ip' => '198.51.100.7', 'country_code' => 'US', 'city_name' => 'Seattle']);

    $session = Session::factory()->create(['ip_address' => '198.51.100.7', 'country_id' => null, 'city' => null]);

    locate($session, null);

    $session->refresh();
    expect($session->country_id)->toBe(Country::where('code', 'us')->value('id'))
        ->and($session->city)->toBe('Seattle');
});

it('does nothing when neither a country nor a resolvable IP is available', function (): void {
    $session = Session::factory()->create(['ip_address' => null, 'country_id' => null]);

    locate($session, null);

    expect($session->refresh()->country_id)->toBeNull();
});

it('never overwrites an already-resolved country', function (): void {
    $bg = Country::where('code', 'bg')->value('id');
    $session = Session::factory()->create(['ip_address' => '203.0.113.10', 'country_id' => $bg]);

    locate($session, 'iq');

    expect($session->refresh()->country_id)->toBe($bg);
});
