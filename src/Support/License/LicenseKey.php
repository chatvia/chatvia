<?php

declare(strict_types=1);

namespace Support\License;

use Illuminate\Support\Str;

/**
 * The terms a license key carries: who bought it, the domains it works on,
 * what it unlocks and, for a yearly key, the last day it works. This is only
 * the content of a key; it is trusted once {@see LicenseSignature::verify()}
 * has checked its signature.
 */
final readonly class LicenseKey
{
    /**
     * @param  list<string>  $domains  Host names, as {@see host()} writes them.
     * @param  list<LicenseFeature>  $features
     * @param  string  $issued  The day the key was made (Y-m-d).
     * @param  string|null  $expires  The last day a yearly key works, grace
     *                                included (Y-m-d); null for a key that never ends.
     */
    public function __construct(
        public string $id,
        public string $licensee,
        public array $domains,
        public array $features,
        public string $issued,
        public ?string $expires = null,
    ) {}

    /** Whether the key works on this host (a port or a trailing dot is ignored). */
    public function coversHost(string $host): bool
    {
        $host = self::host($host);

        return $host !== '' && collect($this->domains)->map(fn (string $domain): string => self::host($domain))->containsStrict($host);
    }

    public function allows(LicenseFeature $feature): bool
    {
        return collect($this->features)->containsStrict($feature);
    }

    /**
     * Whether the last day of a yearly key is over. Checked offline, so a
     * lapsed key stops working even on an install that cannot reach chatvia.ai.
     */
    public function isExpired(): bool
    {
        return $this->expires !== null && now()->toDateString() > $this->expires;
    }

    /** A host name as keys hold it: lowercase, with no port or trailing dot. */
    public static function host(string $host): string
    {
        return Str::of($host)
            ->trim()
            ->lower()
            ->replaceMatches('/:\d+$/', '')
            ->rtrim('.')
            ->toString();
    }

    /**
     * The terms as the key's JSON holds them; `expires` only on a key that ends.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'licensee' => $this->licensee,
            'domains' => $this->domains,
            'features' => array_map(fn (LicenseFeature $feature): string => $feature->value, $this->features),
            'issued' => $this->issued,
            ...($this->expires === null ? [] : ['expires' => $this->expires]),
        ];
    }

    /**
     * The terms read back from a key's JSON, or null when a field is missing
     * or `expires` is not a date. A feature this version does not know is left
     * out, so a newer key still works for what this version can do.
     *
     * @param  array<mixed>  $payload
     */
    public static function fromPayload(array $payload): ?self
    {
        $id = $payload['id'] ?? null;
        $licensee = $payload['licensee'] ?? null;
        $domains = $payload['domains'] ?? null;
        $features = $payload['features'] ?? null;
        $issued = $payload['issued'] ?? null;
        $expires = $payload['expires'] ?? null;

        if (blank($id) || blank($licensee) || blank($issued) || ! is_array($domains) || ! is_array($features)
            || ($expires !== null && ! Str::isMatch('/^\d{4}-\d{2}-\d{2}$/', (string) $expires))) {
            return null;
        }

        return new self(
            id: $id,
            licensee: $licensee,
            domains: array_values(array_filter($domains, filled(...))),
            features: array_values(array_filter(array_map(
                fn (mixed $feature): ?LicenseFeature => filled($feature) ? LicenseFeature::tryFrom($feature) : null,
                $features,
            ))),
            issued: $issued,
            expires: $expires,
        );
    }
}
