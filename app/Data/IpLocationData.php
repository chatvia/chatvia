<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class IpLocationData extends Data
{
    public function __construct(
        public string $ip,
        public ?string $countryCode = null,
        public ?string $countryName = null,
        public ?string $regionName = null,
        public ?string $cityName = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $zipCode = null,
        public ?string $timeZone = null,
        public ?string $asn = null,
        public ?string $as = null,
        public ?bool $isProxy = null,
    ) {}

    /**
     * Create from IP2Location API response.
     *
     * @param  array<string, mixed>  $response
     */
    public static function fromApiResponse(array $response): self
    {
        return new self(
            ip: $response['ip'] ?? '',
            countryCode: isset($response['country_code']) ? strtolower($response['country_code']) : null,
            countryName: $response['country_name'] ?? null,
            regionName: $response['region_name'] ?? null,
            cityName: $response['city_name'] ?? null,
            latitude: isset($response['latitude']) ? (float) $response['latitude'] : null,
            longitude: isset($response['longitude']) ? (float) $response['longitude'] : null,
            zipCode: $response['zip_code'] ?? null,
            timeZone: $response['time_zone'] ?? null,
            asn: $response['asn'] ?? null,
            as: $response['as'] ?? null,
            isProxy: $response['is_proxy'] ?? null,
        );
    }
}
