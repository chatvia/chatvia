<?php

namespace Domain\Billing\Models;

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A purchasable token top-up (manual extra pack or auto-recharge). `amount` is in
 * TOKENS; the pack expires 30 days after purchase (`ends_at`) independent of the
 * base cycle, and is summed into the available balance while active.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property int $amount
 * @property string|null $stripe_id
 * @property Carbon|null $started_at
 * @property Carbon|null $ends_at
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

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
