<?php

namespace App\Identity\Workspace\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A monthly message limit on one account. On a workspace it counts every
 * agent reply of the workspace (a platform admin sets it); on a chatter it
 * counts the chatter's own messages (the workspace sets it). No row means no
 * limit.
 *
 * `messages_used` counts from `period_starts_at`, the first day of a month.
 * When a new month starts the count restarts on the next message, so no
 * scheduled job is needed.
 *
 * @property int $id
 * @property int $user_id
 * @property int $monthly_limit
 * @property int $messages_used
 * @property Carbon $period_starts_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Guarded(['id'])]
class MessageLimit extends Model
{
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
        return $this->usedThisMonth() >= $this->monthly_limit;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
