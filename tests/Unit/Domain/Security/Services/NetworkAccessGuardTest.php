<?php

use Domain\Security\Enums\GeoMode;
use Domain\Security\Services\NetworkAccessGuard;

/**
 * Pure decision logic for the workspace network gate — IP allowlist (fails
 * closed) combined with geo allow/deny (fails OPEN when the country is
 * unresolved, so a geo-IP outage never locks a workspace out of its dashboard).
 */
beforeEach(function (): void {
    $this->guard = app(NetworkAccessGuard::class);
});

it('judges the IP allowlist, failing closed outside it', function (array $allowlist, string $ip, bool $allowed): void {
    expect($this->guard->passes($allowlist, GeoMode::Off, [], $ip, null))->toBe($allowed)
        ->and($this->guard->ipAllowed($allowlist, $ip))->toBe($allowed);
})->with([
    'an empty allowlist' => [[], '203.0.113.9', true],
    'inside an allowlisted CIDR' => [['203.0.113.0/24'], '203.0.113.9', true],
    'an exact allowlisted IP' => [['198.51.100.7'], '198.51.100.7', true],
    'outside the allowlist' => [['203.0.113.0/24'], '198.51.100.7', false],
]);

it('judges the country rule case-insensitively, failing open when the country is unresolved', function (GeoMode $mode, array $countries, ?string $country, bool $allowed): void {
    expect($this->guard->passes([], $mode, $countries, '203.0.113.9', $country))->toBe($allowed)
        ->and($this->guard->geoAllowed($mode, $countries, $country))->toBe($allowed);
})->with([
    'the rule off' => [GeoMode::Off, [], 'US', true],
    'a country on the allow list' => [GeoMode::Allow, ['AE', 'SA'], 'AE', true],
    'a country not on the allow list' => [GeoMode::Allow, ['AE', 'SA'], 'US', false],
    'a country on the deny list' => [GeoMode::Deny, ['RU'], 'RU', false],
    'a country not on the deny list' => [GeoMode::Deny, ['RU'], 'US', true],
    'an allow list in another case' => [GeoMode::Allow, ['ae'], 'AE', true],
    'a deny list in another case' => [GeoMode::Deny, ['RU'], 'ru', false],
    'an unresolved country under an allow list' => [GeoMode::Allow, ['AE'], null, true],
    'an unresolved country under a deny list' => [GeoMode::Deny, ['RU'], null, true],
]);

it('requires both the IP allowlist and the geo rule to pass', function (): void {
    expect($this->guard->passes(['203.0.113.0/24'], GeoMode::Allow, ['AE'], '203.0.113.9', 'US'))->toBeFalse()
        ->and($this->guard->passes(['203.0.113.0/24'], GeoMode::Allow, ['US'], '198.51.100.7', 'US'))->toBeFalse()
        ->and($this->guard->passes(['203.0.113.0/24'], GeoMode::Allow, ['US'], '203.0.113.9', 'US'))->toBeTrue();
});
