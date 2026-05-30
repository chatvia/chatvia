<?php

namespace App\Billing\Models;

use App\Auth\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
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
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Subscription> $subscriptions
 */
#[Guarded(['id'])]
class UserBilling extends Model
{
    use Billable;

    protected $table = 'user_billing';

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
