<?php

use Illuminate\Support\Carbon;
use Support\Enums\User\DateFormat;

it('maps each case to a php format token', function (DateFormat $case, string $expected): void {
    expect(Carbon::create(2026, 6, 8)->format($case->phpFormat()))->toBe($expected);
})->with([
    [DateFormat::Iso, '2026-06-08'],
    [DateFormat::DmySlash, '08/06/2026'],
    [DateFormat::MdySlash, '06/08/2026'],
    [DateFormat::DmyDot, '08.06.2026'],
    [DateFormat::Long, 'Jun 8, 2026'],
]);

it('exposes dropdown options with worked examples', function (): void {
    $options = DateFormat::options();

    expect($options)->toHaveCount(count(DateFormat::cases()))
        ->and($options[0])->toHaveKeys(['value', 'label', 'example']);

    $iso = collect($options)->firstWhere('value', DateFormat::Iso->value);
    expect($iso['example'])->toBe('2026-06-08');
});

it('defaults to iso', function (): void {
    expect(DateFormat::default())->toBe(DateFormat::Iso);
});
