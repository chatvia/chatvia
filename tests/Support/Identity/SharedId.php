<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Illuminate\Support\Facades\DB;

/**
 * An id that is free in two tables at once, so a test can give two kinds of
 * record the same integer id (a workspace and a chatter, a staff account and
 * a chatter) and prove the two never mix (a limit, a cache key, a grant).
 * Taking the next value of both sequences means neither table can hold it yet.
 */
final class SharedId
{
    public static function workspaceAndChatter(): int
    {
        return self::freeIn('workspaces', 'chatters');
    }

    public static function staffAndChatter(): int
    {
        return self::freeIn('users', 'chatters');
    }

    private static function freeIn(string $table, string $otherTable): int
    {
        return (int) DB::scalar(
            "select greatest(nextval(pg_get_serial_sequence(?, 'id')), nextval(pg_get_serial_sequence(?, 'id')))",
            [$table, $otherTable],
        );
    }
}
