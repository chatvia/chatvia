<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_credit_id
 * @property int $messages
 * @property int $credits
 * @property int $messages_exceeded
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
    public function credit(): BelongsTo
    {
        return $this->belongsTo(UserCredit::class);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
