<?php

namespace App\Identity\Workspace\Enums;

use Illuminate\Support\Carbon;

/**
 * Per-user preferred date display format (Settings → Date & time).
 *
 * The enum value is the single source of truth: {@see phpFormat()} renders a
 * date on the server, and the same keys are matched
 * by the frontend `useDateFormat` composable so the dashboard renders dates the
 * customer's way everywhere. Keep the two in lock-step when adding a case.
 */
enum DateFormat: string
{
    case Iso = 'iso';
    case DmySlash = 'dmy_slash';
    case MdySlash = 'mdy_slash';
    case DmyDot = 'dmy_dot';
    case Long = 'long';

    public static function default(): self
    {
        return self::Iso;
    }

    /**
     * PHP `date()`-style token for Carbon `->format()` on the server side.
     */
    public function phpFormat(): string
    {
        return match ($this) {
            self::Iso => 'Y-m-d',
            self::DmySlash => 'd/m/Y',
            self::MdySlash => 'm/d/Y',
            self::DmyDot => 'd.m.Y',
            self::Long => 'M j, Y',
        };
    }

    /**
     * Human label shown next to the example in the settings dropdown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Iso => 'Year-Month-Day',
            self::DmySlash => 'Day/Month/Year',
            self::MdySlash => 'Month/Day/Year',
            self::DmyDot => 'Day.Month.Year',
            self::Long => 'Month Day, Year',
        };
    }

    /**
     * Option metadata for the settings dropdown — value, label, and a worked
     * example (8 June 2026) so the customer sees exactly what each choice yields.
     *
     * @return array<int, array{value: string, label: string, example: string}>
     */
    public static function options(): array
    {
        $sample = Carbon::create(2026, 6, 8);

        return array_map(static fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'example' => $sample->format($case->phpFormat()),
        ], self::cases());
    }
}
