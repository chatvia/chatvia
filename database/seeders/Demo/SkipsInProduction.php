<?php

namespace Database\Seeders\Demo;

/**
 * Guard for seeders that write demo data (the demo admin account, fake chats,
 * fake analytics). They must never touch a production database full of real
 * customers, so they stop with a warning when the app runs in production.
 * Reference data (LLMs, countries, languages) is seeded elsewhere and is not
 * affected.
 */
trait SkipsInProduction
{
    protected function skipInProduction(): bool
    {
        if (! app()->isProduction()) {
            return false;
        }

        $this->command?->warn(static::class.' skipped: demo data is never seeded in production.');

        return true;
    }
}
