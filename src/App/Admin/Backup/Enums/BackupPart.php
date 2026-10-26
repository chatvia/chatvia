<?php

declare(strict_types=1);

namespace App\Admin\Backup\Enums;

/**
 * One part of a backup archive and the file it is in. The archive has the
 * layout of `./manage backup` (docker/manage), so `./manage restore` can put
 * it back: the values are the words of its `HOLDS=` line, the file names its
 * members. Stored in `backups.parts`; never rename a value.
 */
enum BackupPart: string
{
    case Database = 'database';
    case Secrets = 'secrets';
    case Redis = 'redis';
    case Files = 'files';
    case Analytics = 'analytics';
    case Pulse = 'pulse';
    case Spectra = 'spectra';

    /** The file of the part inside the archive. */
    public function member(): string
    {
        return match ($this) {
            self::Database => 'database.dump',
            self::Secrets => 'secrets.env',
            self::Redis => 'redis.rdb',
            self::Files => 'files.tar',
            self::Analytics => 'analytics.dump',
            self::Pulse => 'pulse.dump',
            self::Spectra => 'spectra.dump',
        };
    }

    /** The database connection (config/database.php) a dump part is made from. */
    public function connection(): ?string
    {
        return match ($this) {
            self::Database => (string) config('database.default'),
            self::Analytics => 'analytics',
            self::Pulse => 'pulse',
            self::Spectra => 'spectra',
            self::Secrets, self::Redis, self::Files => null,
        };
    }
}
