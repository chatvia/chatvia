<?php

namespace Domain\Billing\Models;

use Database\Factories\UserUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What one credit cycle used: messages charged (the billing counter) and LLM
 * tokens (analytics only).
 *
 * @property int $id
 * @property int $user_credit_id
 * @property int $messages
 * @property int $credits
 * @property int $messages_exceeded
 * @property int $tokens
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UserCredit|null $credit
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage whereCredits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage whereMessages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage whereMessagesExceeded($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage whereUserCreditId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class UserUsage extends Model
{
    /** @use HasFactory<UserUsageFactory> */
    use HasFactory;

    /**
     * The credit period this usage row counts against. The column is
     * `user_credit_id`, not the `credit_id` Eloquent would guess from the
     * method name.
     *
     * @return BelongsTo<UserCredit, $this>
     */
    public function credit(): BelongsTo
    {
        return $this->belongsTo(UserCredit::class, 'user_credit_id');
    }
}
