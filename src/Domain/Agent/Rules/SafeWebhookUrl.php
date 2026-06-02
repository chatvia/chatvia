<?php

namespace Domain\Agent\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeWebhookUrl implements ValidationRule
{
    /**
     * Validate that a webhook URL is safe to call:
     *   - well-formed
     *   - https only in production
     *   - host does not resolve to a private / loopback / link-local IP
     *
     * The resolved-IP check happens here so we reject obviously-bad URLs at
     * save time; HttpToolDispatcher (slice 3) re-checks at dispatch time
     * because DNS can change.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute must use http or https.');

            return;
        }

        if ($scheme === 'http' && app()->environment('production')) {
            $fail('The :attribute must use https in production.');

            return;
        }

        $host = $parts['host'];

        if (str_contains($host, '..') || preg_match('/[\s<>"]/', $host)) {
            $fail('The :attribute host is malformed.');

            return;
        }

        // In non-prod environments we allow localhost / private IPs so devs
        // can hit local mock servers; production enforces public ranges.
        if (! app()->environment('production')) {
            return;
        }

        $ips = $this->resolve($host);

        if ($ips === []) {
            $fail('The :attribute host could not be resolved.');

            return;
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                $fail('The :attribute resolves to a private or reserved address.');

                return;
            }
        }
    }

    /**
     * @return string[]
     */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $r): ?string => $r['ip'] ?? $r['ipv6'] ?? null,
            $records,
        )));
    }

    public static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }
}
