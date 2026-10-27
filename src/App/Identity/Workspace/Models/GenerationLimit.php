<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Models;

use App\Identity\Workspace\Services\GenerationAllowance;
use Database\Factories\GenerationLimitFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The monthly caps on what a workspace's agents make in chats, set by a
 * platform admin: one on pictures and audio files, one on videos (which
 * cost far more). No row means no cap; a null cap leaves that kind uncapped
 * but still counted. Each file is counted when it is asked for, and given
 * back when making it fails ({@see GenerationAllowance}).
 *
 * `generations_used` and `videos_used` count from `period_starts_at`, the
 * first day of a month. When a new month starts both counts restart with the
 * next file, so no scheduled job is needed.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int|null $monthly_limit Pictures and audio files
 * @property int $generations_used
 * @property int|null $monthly_video_limit
 * @property int $videos_used
 * @property Carbon $period_starts_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workspace $workspace
 */
#[Guarded(['id'])]
class GenerationLimit extends Model
{
    /** @use HasFactory<GenerationLimitFactory> */
    use HasFactory;

    /** Whether the count belongs to the current month. */
    public function isCurrentPeriod(): bool
    {
        return $this->period_starts_at->greaterThanOrEqualTo(now()->startOfMonth());
    }

    /** Pictures and audio files made this month: 0 while the row still holds an earlier month. */
    public function usedThisMonth(): int
    {
        return $this->isCurrentPeriod() ? $this->generations_used : 0;
    }

    /** Videos made this month: 0 while the row still holds an earlier month. */
    public function videosThisMonth(): int
    {
        return $this->isCurrentPeriod() ? $this->videos_used : 0;
    }

    /** Whether this month's pictures and audio files have reached their cap. */
    public function reached(): bool
    {
        return $this->monthly_limit !== null && $this->usedThisMonth() >= $this->monthly_limit;
    }

    /**
     * The workspace whose agents' files this cap counts.
     *
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    protected function casts(): array
    {
        return [
            'monthly_limit' => 'integer',
            'generations_used' => 'integer',
            'monthly_video_limit' => 'integer',
            'videos_used' => 'integer',
            'period_starts_at' => 'datetime',
        ];
    }
}
