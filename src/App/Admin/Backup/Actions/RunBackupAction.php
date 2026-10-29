<?php

declare(strict_types=1);

namespace App\Admin\Backup\Actions;

use App\Admin\Backup\Enums\BackupPart;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Exceptions\BackupException;
use App\Admin\Backup\Models\Backup;
use App\Admin\Backup\Services\BackupEncryption;
use App\Admin\Backup\Services\PostgresDump;
use App\Admin\Backup\Services\RedisSnapshot;
use Illuminate\Http\File as HttpFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Support\Radar\IssueRaised;
use Support\Radar\IssueTypeKey;
use Support\Radar\ServiceKey;
use Support\Radar\ServiceRecovered;
use Throwable;

/**
 * Makes a waiting backup: dumps the databases and Redis into a work folder,
 * packs them in one tar in the layout of `./manage backup` (so `./manage
 * restore` takes it), encrypts it to the admin's public key when one is set,
 * and stores it on the `backups` disk under `backups/`. Then deletes the
 * backups past the number to keep.
 *
 * The app key goes in only when the archive is encrypted: with it, anyone
 * holding a plain archive could read every saved secret. Uploaded files go
 * in only when the admin asked and they are on this server.
 *
 * One backup runs at a time (a cache lock); a second one fails at once and
 * says why. A failure is saved on the row and reported; the work folder is
 * always removed, so no dump stays on the server next to the app key.
 */
class RunBackupAction
{
    public const string DISK = 'backups';

    public const string FOLDER = 'backups';

    /** Longer than the slowest backup; a crashed run frees the lock after it. */
    private const int LOCK_SECONDS = 8 * 3600;

    private const int TAR_TIMEOUT_SECONDS = 4 * 3600;

    public function __construct(
        private readonly PostgresDump $postgres,
        private readonly RedisSnapshot $redis,
        private readonly BackupEncryption $encryption,
        private readonly PruneBackupsAction $prune,
    ) {}

    public function execute(Backup $backup): Backup
    {
        $lock = Cache::lock('backups:run', self::LOCK_SECONDS);

        if (! $lock->get()) {
            $backup->update([
                'status' => BackupStatus::Failed,
                'error' => 'Another backup was running. Try again when it is done.',
                'finished_at' => now(),
            ]);

            return $backup;
        }

        $work = storage_path("framework/backups/{$backup->external_id}");

        try {
            $this->failAbandoned($backup);
            $backup->update(['status' => BackupStatus::Running, 'started_at' => now(), 'error' => null]);
            File::ensureDirectoryExists($work, 0700);

            $this->store($backup, $work);
        } catch (Throwable $e) {
            report($e);

            $backup->update([
                'status' => BackupStatus::Failed,
                'error' => Str::limit($e->getMessage(), 2000),
                'finished_at' => now(),
            ]);
        } finally {
            File::deleteDirectory($work);
            $lock->release();
        }

        if ($backup->status === BackupStatus::Completed) {
            $this->prune->execute(max(1, (int) config('chatvia.backup.keep')));

            ServiceRecovered::dispatch(ServiceKey::BACKUPS);
        } elseif ($backup->status === BackupStatus::Failed) {
            IssueRaised::dispatch(ServiceKey::BACKUPS, IssueTypeKey::BACKUP_FAILED, $backup->error);
        }

        return $backup;
    }

    private function store(Backup $backup, string $work): void
    {
        $publicKey = (string) config('chatvia.backup.public_key');
        $parts = $this->dumpParts($work, encrypted: filled($publicKey));
        $this->writeInfo("{$work}/backup.env", $parts);

        $name = 'chatvia-'.now()->format('Y-m-d-His').'.tar';
        $this->pack($work, $name, $parts);

        $fingerprint = null;

        if (filled($publicKey)) {
            $fingerprint = $this->encryption->encrypt($publicKey, "{$work}/{$name}", "{$work}/{$name}.gpg");
            File::delete("{$work}/{$name}");
            $name .= '.gpg';
        }

        $archive = "{$work}/{$name}";
        // Private: on this server's disk only the app's own user may read it.
        $path = Storage::disk(self::DISK)->putFileAs(self::FOLDER, new HttpFile($archive), $name, ['visibility' => 'private']);

        if ($path === false) {
            throw new BackupException('The backup could not be saved to storage.');
        }

        $backup->update([
            'status' => BackupStatus::Completed,
            'disk' => self::DISK,
            'path' => $path,
            'size' => File::size($archive),
            'sha256' => File::hash($archive, 'sha256'),
            'parts' => $parts,
            'key_fingerprint' => $fingerprint,
            'finished_at' => now(),
        ]);
    }

    /**
     * Writes each part's file into $work; returns the parts, in the order
     * of their `HOLDS=` line.
     *
     * @return list<BackupPart>
     */
    private function dumpParts(string $work, bool $encrypted): array
    {
        $main = (string) config('database.default');
        $this->postgres->dump($main, "{$work}/".BackupPart::Database->member());
        File::put("{$work}/database-extras.sql", $this->postgres->settingsSql($main));
        $parts = [BackupPart::Database];

        if ($encrypted) {
            File::put("{$work}/".BackupPart::Secrets->member(), $this->secrets());
            $parts[] = BackupPart::Secrets;
        }

        $this->redis->dump("{$work}/".BackupPart::Redis->member());
        $parts[] = BackupPart::Redis;

        if (config('chatvia.backup.include_files') && config('filesystems.storage') !== 's3') {
            $this->packFiles("{$work}/".BackupPart::Files->member());
            $parts[] = BackupPart::Files;
        }

        $databases = [BackupPart::Analytics];

        if (config('chatvia.backup.include_tool_databases')) {
            $databases = [...$databases, BackupPart::Pulse, BackupPart::Spectra];
        }

        foreach ($databases as $part) {
            $connection = (string) $part->connection();

            if ($this->postgres->isSeparate($connection) && $this->postgres->exists($connection)) {
                $this->postgres->dump($connection, "{$work}/{$part->member()}");
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * The app key, in the `secrets.env` form of the Docker install. Only the
     * keys: the other secrets of an install (database and Redis passwords)
     * belong to the server it is restored on.
     */
    private function secrets(): string
    {
        $previous = collect((array) config('app.previous_keys'))->filter()->implode(',');

        return collect([
            '# The app key of this install, saved by ChatVia.ai\'s own backup.',
            '# Keep it safe: without it, encrypted data cannot be read.',
            'APP_KEY='.config('app.key'),
            ...(filled($previous) ? ["APP_PREVIOUS_KEYS={$previous}"] : []),
        ])->implode("\n")."\n";
    }

    /**
     * The uploaded files, as `./manage backup --files` saves them (storage/app),
     * without the backups themselves.
     */
    private function packFiles(string $target): void
    {
        $this->tar([
            '-cf', $target,
            '-C', storage_path(),
            '--exclude=app/private/'.self::FOLDER,
            'app',
        ]);
    }

    /**
     * @param  list<BackupPart>  $parts
     */
    private function pack(string $work, string $name, array $parts): void
    {
        $members = collect($parts)
            ->map(fn (BackupPart $part): string => $part->member())
            ->prepend('database-extras.sql')
            ->prepend('backup.env')
            ->all();

        $this->tar(['-cf', "{$work}/{$name}", '-C', $work, ...$members]);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function tar(array $arguments): void
    {
        // COPYFILE_DISABLE: macOS's tar would add a `._` file of extended
        // attributes per member. GNU tar ignores it.
        $result = Process::timeout(self::TAR_TIMEOUT_SECONDS)
            ->env(['COPYFILE_DISABLE' => '1'])
            ->run([(string) config('services.bin.tar'), ...$arguments]);

        if (! $result->successful()) {
            throw new BackupException('tar failed: '.Str::trim($result->errorOutput()));
        }
    }

    /**
     * `backup.env`: what `./manage` reads to describe and restore a backup.
     *
     * @param  list<BackupPart>  $parts
     */
    private function writeInfo(string $path, array $parts): void
    {
        $embeddings = DB::selectOne(<<<'SQL'
            SELECT t.typname || ' ' || a.atttypmod AS storage
            FROM pg_attribute a
            JOIN pg_class c ON c.oid = a.attrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_type t ON t.oid = a.atttypid
            WHERE t.typname IN ('vector', 'halfvec') AND c.relkind IN ('r', 'p')
              AND n.nspname = 'public' AND a.attnum > 0 AND NOT a.attisdropped
            LIMIT 1
            SQL);

        File::put($path, collect([
            '# ChatVia.ai backup, made by the app on '.now()->format('Y-m-d H:i:s').'.',
            'FORMAT=1',
            'CREATED='.now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'VERSION=',
            'LATEST_MIGRATION='.DB::table('migrations')->max('migration'),
            'EMBEDDING_STORAGE='.($embeddings->storage ?? ''),
            'HOLDS='.collect($parts)->map(fn (BackupPart $part): string => $part->value)->implode(' '),
            'MADE_BY=app',
        ])->implode("\n")."\n");
    }

    /**
     * A backup still "running" while this one holds the lock was stopped
     * before it finished (a worker or server went down).
     */
    private function failAbandoned(Backup $backup): void
    {
        Backup::query()
            ->whereKeyNot($backup->getKey())
            ->where('status', BackupStatus::Running)
            ->update([
                'status' => BackupStatus::Failed,
                'error' => 'Stopped before it finished.',
                'finished_at' => now(),
            ]);
    }
}
