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
 * @property string|null $plan
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property Carbon|null $trial_ends_at
 * @property array<string, mixed>|null $billing_address
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
            'billing_address' => 'array',
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

    /**
     * Fetch a Cashier subscription with its `owner` relation pre-wired to this
     * billing row. Cashier's `updateQuantity()` / `cancelNow()` reach for
     * `$subscription->owner` to get the Stripe client; under
     * `Model::shouldBeStrict()` that lazy-load throws, so callers must always
     * set it. Routing every addon caller through here keeps that one Cashier
     * gotcha in a single place.
     */
    public function ownedSubscription(string $type): ?Subscription
    {
        $subscription = $this->subscription($type);

        $subscription?->setRelation('owner', $this);

        return $subscription;
    }

    /**
     * Cashier reads {@see stripeEmail()} and {@see stripeName()} when it
     * auto-creates a Stripe customer on first checkout. Without these the
     * defaults look for `email`/`name` properties on UserBilling itself —
     * which doesn't have either, so Stripe gets an anonymous customer and
     * the checkout page renders with an empty email field. Delegate to the
     * related User so the prefill works and Stripe receipts go to the right
     * address.
     */
    public function stripeEmail(): ?string
    {
        return $this->user?->email;
    }

    public function stripeName(): ?string
    {
        return $this->user?->name;
    }
}
