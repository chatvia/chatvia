<?php

namespace Domain\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A workspace's token allowance for one billing cycle. `tokens` is the granted
 * per-cycle budget — the single credit currency. Consumption accumulates on the
 * related {@see UserUsage}; the send-gate is "available balance > 0".
 *
 * @property int $id
 * @property int $user_id
 * @property int $tokens
 * @property Carbon $started_at
 * @property Carbon $ends_at
 * @property-read UserUsage|null $usage
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserToken whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserToken whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserToken whereTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserToken whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[WithoutTimestamps]
class UserToken extends Model
{
    use HasFactory;

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
