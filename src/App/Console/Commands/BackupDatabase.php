<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * `php artisan app:backup-database` — pg_dump the Postgres database and ship
 * the dump to the private object-storage bucket, off the application host.
 *
 * Scheduled every 12 hours (see routes/console.php). A dump must never stay on
 * the application host, where it would sit next to APP_KEY as plain text: it
 * is written to a temp file, streamed to object storage, and the temp file is
 * removed — nothing lingers on disk.
 *
 * Restore: pull the object, then `pg_restore -d <db> --clean --if-exists
 * --no-owner --no-privileges <file>` (the vector extension — and
 * pg_textsearch, when the install uses it — must be available on the target).
 */
#[Description('Dump the Postgres database with pg_dump and upload it to the private storage bucket, retaining the newest --keep dumps.')]
#[Signature('app:backup-database {--keep=14 : Number of most-recent backups to keep in the bucket; older ones are pruned}')]
class BackupDatabase extends Command
{
    /** The private (access-controlled) bucket disk — see config/filesystems.php. */
    private const string DISK = 'private';

    /** Object-key prefix for database dumps within the bucket. */
    private const string PREFIX = 'backups';

    public function handle(): int
    {
        $keep = (int) $this->option('keep');
        if ($keep < 1) {
            $this->error('--keep must be >= 1.');

            return self::FAILURE;
        }

        $db = config('database.connections.pgsql');

        // Timestamped, lexically-sortable name (app timezone is UTC).
        $remotePath = self::PREFIX.'/chatvia-'.now()->format('Y-m-d_His').'.dump';

        $tmpPath = tempnam(sys_get_temp_dir(), 'chatvia-db-');
        if ($tmpPath === false) {
            $this->error('Could not allocate a temp file for the dump.');

            return self::FAILURE;
        }

        try {
            // -Fc = custom format (compressed). Args passed as an array so no
            // shell is involved; the password goes through PGPASSWORD, never argv.
            $result = Process::timeout(3600)
                ->env(['PGPASSWORD' => (string) ($db['password'] ?? '')])
                ->run([
                    'pg_dump',
                    '-Fc',
                    '--no-owner',
                    '--no-privileges',
                    '-h', (string) ($db['host'] ?? '127.0.0.1'),
                    '-p', (string) ($db['port'] ?? '5432'),
                    '-U', (string) ($db['username'] ?? ''),
                    '-d', (string) ($db['database'] ?? ''),
                    '-f', $tmpPath,
                ]);

            if (! $result->successful()) {
                $this->error('pg_dump failed: '.trim($result->errorOutput()));
                Log::error('Database backup failed.', [
                    'exit_code' => $result->exitCode(),
                    'stderr' => $result->errorOutput(),
                ]);

                return self::FAILURE;
            }

            $stream = fopen($tmpPath, 'rb');
            if ($stream === false) {
                $this->error('Could not open the dump file for upload.');

                return self::FAILURE;
            }

            try {
                Storage::disk(self::DISK)->writeStream($remotePath, $stream);
            } finally {
                fclose($stream);
            }

            $bytes = filesize($tmpPath) ?: 0;
            $this->info(sprintf('Backed up %s (%s) to %s:%s.', $db['database'] ?? '', $this->humanBytes($bytes), self::DISK, $remotePath));
            Log::info('Database backup uploaded.', ['path' => $remotePath, 'bytes' => $bytes]);
        } finally {
            @unlink($tmpPath);
        }

        $this->prune($keep);

        return self::SUCCESS;
    }

    /**
     * Delete every dump in the bucket beyond the newest $keep. Names are
     * timestamped, so a descending lexical sort is newest-first.
     */
    private function prune(int $keep): void
    {
        $disk = Storage::disk(self::DISK);

        $dumps = collect($disk->files(self::PREFIX))
            ->filter(static fn (string $path): bool => str_ends_with($path, '.dump'))
            ->sortDesc()
            ->values();

        $stale = $dumps->slice($keep);
        foreach ($stale as $path) {
            $disk->delete($path);
        }

        if ($stale->isNotEmpty()) {
            $this->info(sprintf('Pruned %d old backup(s); kept %d.', $stale->count(), min($keep, $dumps->count())));
        }
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return sprintf('%.1f %s', $size, $units[$i]);
    }
}
