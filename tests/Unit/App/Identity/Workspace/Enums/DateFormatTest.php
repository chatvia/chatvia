<?php

use App\Identity\Workspace\Enums\DateFormat;
use Illuminate\Support\Carbon;

/**
 * The date formats a person can pick: each value is the PHP format string
 * itself, and the dropdown options carry a worked example.
 */
it('formats a date with each case value', function (DateFormat $case, string $expected): void {
    expect(Carbon::create(2026, 6, 8, 14, 30, 5)->format($case->value))->toBe($expected);
})->with([
    [DateFormat::Iso, '2026-06-08 14:30:05'],
    [DateFormat::YmdSlash, '2026/06/08 14:30:05'],
    [DateFormat::DmySlash, '08/06/2026 14:30'],
    [DateFormat::MdySlash, '06/08/2026 02:30 PM'],
    [DateFormat::DmyDot, '08.06.2026 14:30'],
    [DateFormat::Long, 'Jun 8, 2026 2:30 PM'],
]);

it('exposes dropdown options with worked examples', function (): void {
    $options = DateFormat::options();

    expect($options)->toHaveCount(count(DateFormat::cases()))
        ->and($options[0])->toHaveKeys(['value', 'label', 'example']);

    $iso = collect($options)->firstWhere('value', DateFormat::Iso->value);
    expect($iso['example'])->toBe('2026-06-08 14:30:05');
});
