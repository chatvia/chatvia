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
 * workspace sets it). Exactly one of the two is set. No row means no limit.
 * A workspace row with no `monthly_limit` only counts: an install without a
 * license key keeps the count for the free-use cap, which applies on top of
 * any limit an admin set ({@see MessageAllowance}).
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

    /**
     * The limit in force: the smaller of the one set on this row and $cap
     * (the free-use cap, for a workspace without a license key). Null when
     * neither is set.
     */
    public function effectiveLimit(?int $cap = null): ?int
    {
        $limits = array_filter([$this->monthly_limit, $cap], fn (?int $limit): bool => $limit !== null);

        return $limits === [] ? null : min($limits);
    }

    /** Whether this month's messages have reached the limit in force. */
    public function reached(?int $cap = null): bool
    {
        $limit = $this->effectiveLimit($cap);

        return $limit !== null && $this->usedThisMonth() >= $limit;
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
