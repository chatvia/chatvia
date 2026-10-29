<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Analytics\Enums\TimeBucket;

/**
 * The bucket a chart of a window uses: hours for a day or two, days up to a
 * quarter, months beyond.
 */
it('picks hours for a day or two, days up to a quarter, months beyond', function (string $start, string $end, TimeBucket $expected): void {
    expect(TimeBucket::spanning(CarbonImmutable::parse($start), CarbonImmutable::parse($end)))->toBe($expected);
})->with([
    'two days' => ['2031-03-19 00:00:00', '2031-03-20 23:59:59', TimeBucket::Hour],
    'a quarter' => ['2031-01-01 00:00:00', '2031-04-02 23:59:59', TimeBucket::Day],
    'half a year' => ['2031-01-01 00:00:00', '2031-06-30 23:59:59', TimeBucket::Month],
]);
