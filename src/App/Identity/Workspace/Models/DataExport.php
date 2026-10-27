<?php

namespace App\Identity\Workspace\Models;

use App\Identity\Workspace\Enums\DataExportStatus;
use Database\Factories\DataExportFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A GDPR export of an account's data: its status, the ZIP on the private local
 * disk, and when the download link expires.
 *
 * @property int $id
 * @property int $user_id
 * @property DataExportStatus $status
 * @property string|null $file_path
 * @property int|null $file_size
 * @property string $download_token
 * @property Carbon|null $expires_at
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
#[Guarded(['id'])]
class DataExport extends Model
{
    /** @use HasFactory<DataExportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => DataExportStatus::class,
            'expires_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReady(): bool
    {
        return $this->status === DataExportStatus::Ready;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        if (collect([DataExportStatus::Pending, DataExportStatus::Processing])->containsStrict($this->status)) {
            return true;
        }

        return $this->status === DataExportStatus::Ready && ! $this->isExpired();
    }
}
