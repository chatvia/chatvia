<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Illuminate\Support\Facades\DB;

/**
 * An id that is free in two tables at once, so a test can give a workspace
 * and a chatter the same integer id and prove the two never mix (a limit, a
 * cache key, a grant). Taking the next value of both sequences means neither
 * table can hold it yet.
 */
final class SharedId
{
    public static function workspaceAndChatter(): int
    {
        return (int) DB::scalar("select greatest(nextval(pg_get_serial_sequence('workspaces', 'id')), nextval(pg_get_serial_sequence('chatters', 'id')))");
    }
}
