<?php

namespace App\Identity\Workspace\Models;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Services\MessageAllowance;
use Database\Factories\MessageLimitFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A monthly message limit on one account. On a workspace (`workspace_id`) it
 * counts every agent reply of the workspace (a platform admin sets it); on a
 * chatter (`chatter_id`) it counts the chatter's own messages (the
 * workspace sets it). Exactly one of the two is set. No row means no limit,
 * and a row with no `monthly_limit` only keeps this month's count
 * ({@see MessageAllowance}).
 *
 * `messages_used` counts from `period_starts_at`, the first day of a month.
 * When a new month starts the count restarts on the next message, so no
 * scheduled job is needed.
 *
 * @property int $id
 * @property int|null $workspace_id
 * @property int|null $chatter_id
 * @property int|null $monthly_limit
 * @property int $messages_used
 * @property Carbon $period_starts_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workspace|null $workspace
 * @property-read Chatter|null $chatter
 */
#[Guarded(['id'])]
class MessageLimit extends Model
{
    /** @use HasFactory<MessageLimitFactory> */
    use HasFactory;

    /** Whether the count belongs to the current month. */
    public function isCurrentPeriod(): bool
    {
        return $this->period_starts_at->greaterThanOrEqualTo(now()->startOfMonth());
    }

    /** Messages used this month: 0 while the row still holds an earlier month. */
    public function usedThisMonth(): int
    {
        return $this->isCurrentPeriod() ? $this->messages_used : 0;
    }

    /** Whether this month's messages have reached the limit. */
    public function reached(): bool
    {
        return $this->monthly_limit !== null && $this->usedThisMonth() >= $this->monthly_limit;
    }

    /**
     * The workspace whose agent replies this limit counts, if it is a
     * workspace's.
     *
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The chatter whose own messages this limit counts, if it is a chatter's.
     *
     * @return BelongsTo<Chatter, $this>
     */
    public function chatter(): BelongsTo
    {
        return $this->belongsTo(Chatter::class);
    }

    protected function casts(): array
    {
        return [
            'monthly_limit' => 'integer',
            'messages_used' => 'integer',
            'period_starts_at' => 'datetime',
        ];
    }
}
