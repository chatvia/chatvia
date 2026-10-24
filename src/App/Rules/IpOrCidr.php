<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Accepts a single IPv4/IPv6 address or a CIDR range (`203.0.113.0/24`,
 * `2001:db8::/32`) whose prefix length fits the address family.
 */
class IpOrCidr implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! $this->isIpOrCidr((string) $value)) {
            $fail(__('Each entry must be a valid IP address or CIDR range.'));
        }
    }

    private function isIpOrCidr(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (! Str::contains($value, '/')) {
            return false;
        }

        [$ip, $prefix] = explode('/', $value, 2);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false || ! ctype_digit($prefix)) {
            return false;
        }

        $maxPrefix = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;

        return (int) $prefix <= $maxPrefix;
    }
}
