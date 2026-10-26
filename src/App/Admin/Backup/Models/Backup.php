<?php

declare(strict_types=1);

namespace App\Admin\Backup\Models;

use App\Admin\Backup\Enums\BackupPart;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Enums\BackupTrigger;
use Database\Factories\BackupFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Support\Concerns\HasExternalId;

/**
 * One backup of the install: the database, Redis and, by the admin's
 * choice, the uploaded files and the other databases, in one archive on the
 * `private` disk under `backups/`. With a public key in Settings → Backups
 * the archive is encrypted to it, and only then does it hold the app key.
 * The row stays after a failure, so the admin sees why.
 *
 * @property int $id
 * @property string $external_id
 * @property BackupStatus $status
 * @property BackupTrigger $trigger
 * @property string|null $disk
 * @property string|null $path
 * @property int|null $size
 * @property string|null $sha256
 * @property Collection<int, BackupPart>|null $parts
 * @property string|null $key_fingerprint
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class Backup extends Model
{
    /** @use HasFactory<BackupFactory> */
    use HasExternalId, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BackupStatus::class,
            'trigger' => BackupTrigger::class,
            'size' => 'integer',
            'parts' => AsEnumCollection::of(BackupPart::class),
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isEncrypted(): bool
    {
        return filled($this->key_fingerprint);
    }
}
