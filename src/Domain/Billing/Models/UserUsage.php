<?php

namespace Domain\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tokens consumed in a billing cycle. Accumulator only — `tokens` is ALLOWED to
 * exceed the granted budget on the {@see UserToken} (the turn that crosses zero
 * overshoots); the available balance is clamped at 0 elsewhere.
 *
 * @property int $id
 * @property int $user_token_id
 * @property int $tokens
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UserToken|null $token
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage whereTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUsage whereUserTokenId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class UserUsage extends Model
{
    use HasFactory;

    public function token(): BelongsTo
    {
        return $this->belongsTo(UserToken::class);
    }
}
