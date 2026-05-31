<?php

namespace App\Billing\Models;

use App\Auth\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property int $amount
 * @property string|null $stripe_id
 * @property string|null $started_at
 * @property string|null $ends_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAddon newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAddon newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAddon query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAddon whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAddon whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAddon whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAddon whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAddon whereStripeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAddon whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAddon whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[WithoutTimestamps]
class UserAddon extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
