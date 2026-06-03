<?php

namespace Domain\Billing\Models;

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;

/**
 * Stripe-side state for a billable `User`. Carries the Cashier `Billable`
 * trait — `User` no longer does. One row per user that ever needs billing
 * (auto-provisioned for `type=workspace` accounts).
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property Carbon|null $trial_ends_at
 * @property bool $auto_recharge_enabled
 * @property int $auto_recharge_threshold
 * @property int $auto_recharge_amount
 * @property Carbon|null $auto_recharge_last_triggered_at
 * @property-read User $user
 * @property-read Collection<int, Subscription> $subscriptions
 */
#[Guarded(['id'])]
#[Table(name: 'user_billing')]
class UserBilling extends Model
{
    use Billable;

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'auto_recharge_enabled' => 'boolean',
            'auto_recharge_threshold' => 'integer',
            'auto_recharge_amount' => 'integer',
            'auto_recharge_last_triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
