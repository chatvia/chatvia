<?php

namespace Database\Seeders\Demo;

use Support\Localization\Country\Models\Country;

/**
 * Where the demo store's visitors come from: the markets Halden Home ships
 * to, each with a share of the traffic, the browser language and a few
 * cities with their time zones. The chat sessions and the analytics history
 * both pick through here, so the "by country" panel, the session locations
 * and the hours people chat at agree with each other.
 */
class DemoCountries
{
    /**
     * Lowercase ISO-3166-1 alpha-2 code (as in `countries.code`) → share of
     * the traffic, browser language and city → IANA time zone.
     *
     * @var array<string, array{weight: int, locale: string, cities: array<string, string>}>
     */
    public const array COUNTRIES = [
        'us' => ['weight' => 34, 'locale' => 'en', 'cities' => [
            'New York' => 'America/New_York', 'Austin' => 'America/Chicago', 'Chicago' => 'America/Chicago',
            'Denver' => 'America/Denver', 'Seattle' => 'America/Los_Angeles', 'San Diego' => 'America/Los_Angeles',
        ]],
        'gb' => ['weight' => 14, 'locale' => 'en', 'cities' => [
            'London' => 'Europe/London', 'Manchester' => 'Europe/London', 'Bristol' => 'Europe/London', 'Leeds' => 'Europe/London',
        ]],
        'ca' => ['weight' => 9, 'locale' => 'en', 'cities' => [
            'Toronto' => 'America/Toronto', 'Vancouver' => 'America/Vancouver', 'Calgary' => 'America/Edmonton',
        ]],
        'de' => ['weight' => 9, 'locale' => 'de', 'cities' => [
            'Berlin' => 'Europe/Berlin', 'Munich' => 'Europe/Berlin', 'Hamburg' => 'Europe/Berlin', 'Cologne' => 'Europe/Berlin',
        ]],
        'nl' => ['weight' => 5, 'locale' => 'nl', 'cities' => [
            'Amsterdam' => 'Europe/Amsterdam', 'Utrecht' => 'Europe/Amsterdam', 'Rotterdam' => 'Europe/Amsterdam',
        ]],
        'fr' => ['weight' => 5, 'locale' => 'fr', 'cities' => [
            'Paris' => 'Europe/Paris', 'Lyon' => 'Europe/Paris', 'Bordeaux' => 'Europe/Paris',
        ]],
        'es' => ['weight' => 5, 'locale' => 'es', 'cities' => [
            'Madrid' => 'Europe/Madrid', 'Barcelona' => 'Europe/Madrid', 'Valencia' => 'Europe/Madrid',
        ]],
        'au' => ['weight' => 5, 'locale' => 'en', 'cities' => [
            'Sydney' => 'Australia/Sydney', 'Melbourne' => 'Australia/Melbourne', 'Brisbane' => 'Australia/Brisbane',
        ]],
        'se' => ['weight' => 4, 'locale' => 'sv', 'cities' => [
            'Stockholm' => 'Europe/Stockholm', 'Gothenburg' => 'Europe/Stockholm', 'Malmö' => 'Europe/Stockholm',
        ]],
        'ie' => ['weight' => 3, 'locale' => 'en', 'cities' => [
            'Dublin' => 'Europe/Dublin', 'Cork' => 'Europe/Dublin',
        ]],
        'in' => ['weight' => 4, 'locale' => 'en', 'cities' => [
            'Bengaluru' => 'Asia/Kolkata', 'Mumbai' => 'Asia/Kolkata', 'Pune' => 'Asia/Kolkata',
        ]],
        'mx' => ['weight' => 3, 'locale' => 'es', 'cities' => [
            'Mexico City' => 'America/Mexico_City', 'Guadalajara' => 'America/Mexico_City',
        ]],
    ];

    /** @var array<string, int>|null code → countries.id, loaded once. */
    private static ?array $codeToId = null;

    /**
     * A weighted country code, or a random one of $codes when given (a chat
     * written in German comes from a German-speaking market).
     *
     * @param  list<string>  $codes
     */
    public static function pickCode(array $codes = []): string
    {
        if ($codes !== []) {
            return $codes[array_rand($codes)];
        }

        return DemoRandom::pick(array_map(fn (array $country): int => $country['weight'], self::COUNTRIES));
    }

    /**
     * A visitor location: the country (code for analytics, id for the
     * session row), a city in it, its time zone and the browser language.
     *
     * @param  list<string>  $codes
     * @return array{code: string, id: int|null, city: string, timezone: string, locale: string}
     */
    public static function pick(array $codes = []): array
    {
        return self::in(self::pickCode($codes));
    }

    /**
     * A random city of the given country.
     *
     * @return array{code: string, id: int|null, city: string, timezone: string, locale: string}
     */
    public static function in(string $code): array
    {
        $country = self::COUNTRIES[$code];
        $city = array_rand($country['cities']);

        return [
            'code' => $code,
            'id' => self::idForCode($code),
            'city' => $city,
            'timezone' => $country['cities'][$city],
            'locale' => $country['locale'],
        ];
    }

    public static function idForCode(string $code): ?int
    {
        self::$codeToId ??= Country::query()
            ->whereIn('code', array_keys(self::COUNTRIES))
            ->pluck('id', 'code')
            ->all();

        return self::$codeToId[$code] ?? null;
    }
}
