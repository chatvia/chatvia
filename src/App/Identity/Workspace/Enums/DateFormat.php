<?php

namespace App\Identity\Workspace\Enums;

use Illuminate\Support\Carbon;

/**
 * Per-user preferred date and time display format (Settings → Date & time).
 *
 * Each value is the PHP `date()` format string itself, stored as-is in
 * `users.date_format`: the server formats with it directly and the frontend
 * `useDateFormat` composable translates its tokens to dayjs, so a new case
 * only needs tokens that composable knows.
 */
enum DateFormat: string
{
    case Iso = 'Y-m-d H:i:s';
    case YmdSlash = 'Y/m/d H:i:s';
    case DmySlash = 'd/m/Y H:i';
    case MdySlash = 'm/d/Y h:i A';
    case DmyDot = 'd.m.Y H:i';
    case Long = 'M j, Y g:i A';

    public static function default(): self
    {
        return self::Iso;
    }

    /**
     * Human label shown next to the example in the settings dropdown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Iso => 'Year-Month-Day, 24-hour',
            self::YmdSlash => 'Year/Month/Day, 24-hour',
            self::DmySlash => 'Day/Month/Year, 24-hour',
            self::MdySlash => 'Month/Day/Year, 12-hour',
            self::DmyDot => 'Day.Month.Year, 24-hour',
            self::Long => 'Month Day, Year, 12-hour',
        };
    }

    /**
     * Option metadata for the settings dropdown — value, label, and a worked
     * example (8 June 2026, 14:30:05) so the customer sees exactly what each
     * choice yields.
     *
     * @return array<int, array{value: string, label: string, example: string}>
     */
    public static function options(): array
    {
        $sample = Carbon::create(2026, 6, 8, 14, 30, 5);

        return collect(self::cases())
            ->map(static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'example' => $sample->format($case->value),
            ])
            ->all();
    }
}
