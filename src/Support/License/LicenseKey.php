<?php

declare(strict_types=1);

namespace Support\License;

/**
 * The terms a license key carries: who bought it, the domains it works on and
 * what it unlocks. This is only the content of a key; it is trusted once
 * {@see LicenseSignature::verify()} has checked its signature.
 */
final readonly class LicenseKey
{
    /**
     * @param  list<string>  $domains  Host names, as {@see host()} writes them.
     * @param  list<LicenseFeature>  $features
     * @param  string  $issued  The day the key was made (Y-m-d).
     */
    public function __construct(
        public string $id,
        public string $licensee,
        public array $domains,
        public array $features,
        public string $issued,
    ) {}

    /** Whether the key works on this host (a port or a trailing dot is ignored). */
    public function coversHost(string $host): bool
    {
        $host = self::host($host);

        return $host !== '' && in_array($host, array_map(self::host(...), $this->domains), true);
    }

    public function allows(LicenseFeature $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    /** A host name as keys hold it: lowercase, with no port or trailing dot. */
    public static function host(string $host): string
    {
        $host = strtolower(trim($host));

        return rtrim((string) preg_replace('/:\d+$/', '', $host), '.');
    }

    /**
     * The terms as the key's JSON holds them.
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
        ];
    }

    /**
     * The terms read back from a key's JSON, or null when a field is missing.
     * A feature this version does not know is left out, so a newer key still
     * works for what this version can do.
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

        if (! is_string($id) || ! is_string($licensee) || ! is_string($issued) || ! is_array($domains) || ! is_array($features)) {
            return null;
        }

        return new self(
            id: $id,
            licensee: $licensee,
            domains: array_values(array_filter($domains, is_string(...))),
            features: array_values(array_filter(array_map(
                fn (mixed $feature): ?LicenseFeature => is_string($feature) ? LicenseFeature::tryFrom($feature) : null,
                $features,
            ))),
            issued: $issued,
        );
    }
}
