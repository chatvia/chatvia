<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Carbon;

/**
 * The random helpers every demo seeder shares, so the chats and the analytics
 * history draw from the same distributions: weighted picks, percentages and
 * a visitor's local business-hours start time.
 */
class DemoRandom
{
    /**
     * Local hour-of-day weights: people chat mostly during the working day
     * and the evening, little at night.
     */
    public const array LOCAL_HOUR_WEIGHTS = [
        0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
        6 => 2, 7 => 4, 8 => 6,
        9 => 8, 10 => 9, 11 => 9, 12 => 8, 13 => 8, 14 => 9, 15 => 9, 16 => 8,
        17 => 7, 18 => 6, 19 => 6, 20 => 6, 21 => 5, 22 => 3, 23 => 2,
    ];

    /**
     * A key of $weights, picked in proportion to its weight.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, int>  $weights
     * @return TKey
     */
    public static function pick(array $weights): int|string
    {
        $roll = random_int(1, max(1, array_sum($weights)));

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /** True $percent times out of a hundred. */
    public static function chance(int $percent): bool
    {
        return random_int(1, 100) <= $percent;
    }

    /** A float between $min and $max, rounded to $precision places. */
    public static function between(float $min, float $max, int $precision = 2): float
    {
        return round($min + (random_int(0, 10_000) / 10_000) * ($max - $min), $precision);
    }

    /**
     * How many questions a visitor asks in one chat, 1 to $max: most chats
     * are one or two questions, a few run long.
     */
    public static function turns(float $mean, int $max = 7): int
    {
        $sample = -log(random_int(1, 999_999) / 1_000_000) * $mean;

        return max(1, min($max, (int) round($sample)));
    }

    /**
     * A tone that fits $sentiment, weighted by $weights: a happy visitor is
     * never "frustrated", an unhappy one never "satisfied".
     *
     * @param  array<string, int>  $weights
     */
    public static function toneFor(string $sentiment, array $weights): string
    {
        $fits = match ($sentiment) {
            'positive' => ['satisfied', 'neutral'],
            'negative' => ['frustrated', 'urgent', 'confused', 'neutral'],
            default => ['neutral', 'confused'],
        };

        return (string) self::pick(array_intersect_key($weights, array_flip($fits)) ?: ['neutral' => 1]);
    }

    /**
     * When a visitor in $timezone starts a chat on $day (a calendar date in
     * their time zone), in UTC. Null when the time would be in the future or
     * too recent to have a finished chat.
     */
    public static function localStart(Carbon $day, string $timezone): ?Carbon
    {
        $start = Carbon::parse($day->toDateString(), $timezone)
            ->setTime(self::pick(self::LOCAL_HOUR_WEIGHTS), random_int(0, 59), random_int(0, 59))
            ->utc();

        return $start->lessThan(now()->subHour()) ? $start : null;
    }
}
