<?php

declare(strict_types=1);

namespace App\Admin\Backup\Enums;

use App\Admin\Settings\BackupSettings;
use Illuminate\Support\Str;

/**
 * A program on the server that a backup runs. Its path is a setting
 * (Settings → Backups → Programs), for an install whose programs are not on
 * the PATH of the scheduler or the queue worker. The value is the program's
 * own name.
 *
 * Only that name, or a full path that ends in it, is accepted
 * ({@see acceptsPath()}): any other path would let a stolen admin login make
 * the server run a command of its choice.
 */
enum BackupProgram: string
{
    case PgDump = 'pg_dump';
    case PgRestore = 'pg_restore';
    case RedisCli = 'redis-cli';
    case Gpg = 'gpg';

    /** The {@see BackupSettings} property that holds its path. */
    public function property(): string
    {
        return Str::replace('-', '_', $this->value).'_path';
    }

    /** What a backup runs it for, shown under its field. */
    public function purpose(): string
    {
        return match ($this) {
            self::PgDump => 'Copies the databases. It must be the version of the PostgreSQL server or newer. Part of the PostgreSQL client.',
            self::PgRestore => 'Reads each database copy back, to check it is complete. Part of the PostgreSQL client.',
            self::RedisCli => 'Copies the Redis data: who is signed in and the chat memory. Part of Redis.',
            self::Gpg => 'Encrypts the backups with your public key. Needed only when a public key is saved.',
        };
    }

    /** Whether backups need it with the settings in use. */
    public function isNeeded(): bool
    {
        return $this !== self::Gpg || filled(config('chatvia.backup.public_key'));
    }

    /**
     * Whether the path may be saved for this program: its own name, looked
     * up on the PATH, or a full path whose last part is its name.
     */
    public function acceptsPath(string $path): bool
    {
        return $path === $this->value
            || (Str::startsWith($path, '/') && Str::endsWith($path, "/{$this->value}") && ! Str::isMatch('/[\x00-\x1F\x7F]/', $path));
    }
}
