<?php

use Domain\Security\Data\NetworkRulesData;
use Domain\Security\Enums\GeoMode;
use Domain\Security\Enums\IpAllowlistMode;

/**
 * A workspace's network rules as the request gate reads them: an IP
 * allowlist switched off restricts nothing even when it still lists
 * addresses.
 */
function nrdRules(IpAllowlistMode $ipMode, GeoMode $geoMode): NetworkRulesData
{
    return new NetworkRulesData($ipMode, ['203.0.113.0/24'], $geoMode, ['DK']);
}

it('enforces the allowlist only while it is on', function (IpAllowlistMode $ipMode, array $enforced): void {
    expect(nrdRules($ipMode, GeoMode::Allow)->enforcedIpAllowlist())->toBe($enforced);
})->with([
    'on' => [IpAllowlistMode::Dashboard, ['203.0.113.0/24']],
    'off, with addresses left in it' => [IpAllowlistMode::Off, []],
]);
