<?php

use Domain\Security\Data\NetworkRulesData;
use Domain\Security\Enums\GeoMode;
use Domain\Security\Enums\IpAllowlistMode;

/**
 * A workspace's network rules as the request gate reads them: there is
 * something to enforce while either rule is on, an IP allowlist switched off
 * restricts nothing even when it still lists addresses, and the rules come
 * back the same from the cache.
 */
function nrdRules(IpAllowlistMode $ipMode, GeoMode $geoMode): NetworkRulesData
{
    return new NetworkRulesData($ipMode, ['203.0.113.0/24'], $geoMode, ['DK']);
}

it('has something to enforce while either rule is on', function (IpAllowlistMode $ipMode, GeoMode $geoMode, bool $active): void {
    expect(nrdRules($ipMode, $geoMode)->isActive())->toBe($active);
})->with([
    'both off' => [IpAllowlistMode::Off, GeoMode::Off, false],
    'the allowlist on' => [IpAllowlistMode::Dashboard, GeoMode::Off, true],
    'only the country rule on' => [IpAllowlistMode::Off, GeoMode::Allow, true],
]);

it('enforces the allowlist only while it is on', function (IpAllowlistMode $ipMode, array $enforced): void {
    expect(nrdRules($ipMode, GeoMode::Allow)->enforcedIpAllowlist())->toBe($enforced);
})->with([
    'on' => [IpAllowlistMode::Dashboard, ['203.0.113.0/24']],
    'off, with addresses left in it' => [IpAllowlistMode::Off, []],
]);

it('comes back from the cache as it went in', function (): void {
    $rules = nrdRules(IpAllowlistMode::Dashboard, GeoMode::Deny);

    expect(NetworkRulesData::fromCache($rules->toCache()))->toEqual($rules);
});
