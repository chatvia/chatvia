<?php

namespace Database\Seeders\Demo;

use App\Country\Models\Country;

/**
 * Single source of truth for the country mix used by demo seeders.
 *
 * Picking through this helper guarantees that the chat-log sessions, the
 * analytics rollups, the leads, and anything else seeded for the demo all
 * pull from the same weighted distribution — so the "by country" panel and
 * the per-session locations stay coherent.
 */
class DemoCountries
{
    /**
     * ISO-3166-1 alpha-2 codes (lowercase to match countries.code) with
     * rough share weights. The numbers don't need to sum to 100 — pickCode()
     * normalizes against the total.
     */
    public const WEIGHTS = [
        'us' => 30, 'gb' => 12, 'in' => 10, 'de' => 8, 'br' => 6,
        'se' => 5, 'fr' => 4, 'nl' => 4, 'au' => 3, 'jp' => 3,
        'ca' => 3, 'es' => 3, 'it' => 3, 'mx' => 3, 'pl' => 3,
    ];

    /** @var array<string, int>|null code → countries.id, lazy-loaded once. */
    private static ?array $codeToId = null;

    /**
     * Pick a weighted country code (lowercase, e.g. "us", "gb").
     */
    public static function pickCode(): string
    {
        $total = array_sum(self::WEIGHTS);
        $r = random_int(1, $total);
        $acc = 0;

        foreach (self::WEIGHTS as $code => $weight) {
            $acc += $weight;
            if ($r <= $acc) {
                return $code;
            }
        }

        return array_key_last(self::WEIGHTS);
    }

    /**
     * Pick a weighted country and return both the code and the countries.id
     * resolved from the main DB. Both are needed when seeding chat sessions
     * (id for the FK, code for the analytics rollup snapshot).
     *
     * @return array{code: string, id: int|null}
     */
    public static function pick(): array
    {
        $code = self::pickCode();

        return [
            'code' => $code,
            'id' => self::idForCode($code),
        ];
    }

    public static function idForCode(string $code): ?int
    {
        self::$codeToId ??= Country::query()
            ->whereIn('code', array_keys(self::WEIGHTS))
            ->pluck('id', 'code')
            ->all();

        return self::$codeToId[$code] ?? null;
    }
}
