<?php

namespace Domain\Analytics\Services;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The database the analytics tables live in (`analytics_events`, the hourly
 * and daily rollups, per-conversation metrics). It is the default connection
 * unless config('chatvia.analytics.connection') points at a separate Postgres
 * database, so every analytics read and write goes through here instead of
 * picking a connection itself.
 */
class AnalyticsDatabase
{
    public function connection(): Connection
    {
        return DB::connection(config('chatvia.analytics.connection'));
    }

    public function table(string $table): Builder
    {
        return $this->connection()->table($table);
    }
}
