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

    /**
     * The version of pg_partman in the analytics database, or null without
     * it: then `analytics_events` has only its default partition, so the log
     * is one table that grows, and old events are deleted row by row.
     */
    public function partmanVersion(): ?string
    {
        return $this->table('pg_extension')->where('extname', 'pg_partman')->value('extversion');
    }
}
