<?php

declare(strict_types=1);

namespace Domain\Guardrail\Services\Detectors\Filters;

/**
 * Masks IPv4 and IPv6 addresses ([IP_n]); under the GDPR an IP address is
 * personal data. A candidate counts only when PHP accepts it as an address,
 * so version numbers and times stay readable. Opt-in: technical support
 * often needs the address itself.
 */
final class IpAddressFilter extends PiiFilter
{
    protected function pattern(): string
    {
        return '/(?<![\w.:])(?:\d{1,3}(?:\.\d{1,3}){3}|[0-9a-f]{0,4}(?::[0-9a-f]{0,4}){2,7})(?![\w.:])/i';
    }

    protected function label(): string
    {
        return 'IP';
    }

    protected function type(): string
    {
        return 'ip_address';
    }

    protected function accepts(string $value): bool
    {
        return preg_match('/[0-9a-f]/i', $value) === 1
            && filter_var($value, FILTER_VALIDATE_IP) !== false;
    }
}
