<?php

namespace Domain\Billing\Models;

use Database\Factories\UserCreditFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One billing cycle of a message pool: `messages` is the allowance,
 * `[started_at, ends_at]` the window. Workspace owners get one per plan cycle;
 * a chatter with a monthly cap gets one per month.
 *
 * @property int $id
 * @property int $user_id
 * @property int $messages
 * @property Carbon $started_at
 * @property Carbon $ends_at
 * @property-read UserUsage|null $usage
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereMessages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[WithoutTimestamps]
class UserCredit extends Model
{
    /** @use HasFactory<UserCreditFactory> */
    use HasFactory;

    /**
     * @return HasOne<UserUsage, $this>
     */
    public function usage(): HasOne
    {
        return $this->hasOne(UserUsage::class);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
