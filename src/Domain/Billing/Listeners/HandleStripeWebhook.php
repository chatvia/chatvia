<?php

namespace Domain\Billing\Listeners;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Domain\Billing\Actions\Subscriptions\GrantSubscriptionAction;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Models\UserBilling;
use Domain\Billing\Notifications\TrialWelcomeNotification;
use Domain\Membership\Team\Services\TeamService;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Subscription;
use Support\Enums\User\UserType;
use Throwable;

class HandleStripeWebhook
{
    public function __construct(
        private readonly PasswordSetup $passwordSetup,
    ) {}

    public function handle(WebhookReceived $event): void
    {
        $eventId = $event->payload['id'] ?? null;

        // Idempotency: Stripe retries webhooks on transient failures, so the
        // same evt_* can arrive multiple times. Cache::add is atomic; a false
        // return means we've already processed this event and should skip.
        if ($eventId && ! Cache::add("stripe:event:{$eventId}", true, now()->addDays(7))) {
            Log::info('Stripe webhook replay skipped', ['event_id' => $eventId]);

            return;
        }

        try {
            $this->process($event);
        } catch (Throwable $exception) {
            // Release the idempotency claim so Stripe's retry can reprocess
            // the event — otherwise a transient failure here would mark the
            // event as handled and permanently drop it.
            if ($eventId) {
                Cache::forget("stripe:event:{$eventId}");
            }

            throw $exception;
        }
    }

    private function process(WebhookReceived $event): void
    {
        $data = $event->payload['data']['object'];

        $type = $event->payload['type'];

        // Detect if this is a trial registration event
        $isTrial = $type === 'checkout.session.completed' &&
            isset($data['metadata']['registration_type']) &&
            $data['metadata']['registration_type'] === 'trial_registration';

        // Handle trial registration via checkout.session.completed
        if ($isTrial) {
            $this->handleTrialRegistration($event);

            return;
        }

        // Handle subscription renewals and actual payments
        if ($type === 'invoice.payment_succeeded' && isset($data['subscription_details']['metadata'])) {
            $this->handleInvoicePaymentSucceeded($event);

            return;
        }

        // Handle addon purchases (using 'type' field for addons is fine since they don't create subscriptions)
        if ($type === 'checkout.session.completed' && isset($data['metadata']['type']) && $data['metadata']['type'] === 'addon') {
            $this->handleAddOns($data);
        }

        // Handle addon-subscription checkouts (extra agents / custom domains)
        if ($type === 'checkout.session.completed' && isset($data['metadata']['type']) && $data['metadata']['type'] === 'addon_subscription') {
            $this->handleAddonSubscriptionCreated($data);
        }

        // Cashier already syncs the local `subscriptions` row on
        // customer.subscription.deleted; here we just reset the user_quotas
        // fields we own.
        if ($type === 'customer.subscription.deleted') {
            $this->handleAddonSubscriptionDeleted($data);
        }

        // Log customer.subscription.updated events specifically
        if ($type === 'customer.subscription.updated') {
            Log::info('Subscription status updated via webhook', [
                'subscription_id' => $data['id'] ?? null,
                'status' => $data['status'] ?? null,
                'customer' => $data['customer'] ?? null,
            ]);

            $this->handleAddonSubscriptionUpdated($data);
        }
    }

    /**
     * Handle invoice.payment_succeeded event.
     *
     * This method handles:
     * - Subscription renewals (billing_reason: 'subscription_cycle')
     * - Trial-to-paid conversions when actual charge happens
     *
     * It SKIPS:
     * - Trial start invoices (billing_reason: 'subscription_create')
     *   Those are handled via checkout.session.completed event.
     */
    private function handleInvoicePaymentSucceeded(WebhookReceived $event): void
    {
        $data = $event->payload['data']['object'];

        $billingReason = $data['billing_reason'] ?? null;

        // Skip trial invoices (billing_reason: 'subscription_create')
        // Trial registration is handled via checkout.session.completed event
        if ($billingReason === 'subscription_create') {
            Log::info('Skipping trial invoice - already handled via checkout.session.completed', [
                'invoice_id' => $data['id'] ?? null,
                'customer' => $data['customer'] ?? null,
            ]);

            return;
        }

        $billing = UserBilling::query()->where('stripe_id', $data['customer'])->first();
        $user = $billing?->user;

        if (! $user) {
            Log::warning('User not found for invoice payment', ['customer' => $data['customer']]);

            return;
        }

        // Handle subscription renewals
        if ($billingReason === 'subscription_cycle') {
            $metadata = $data['subscription_details']['metadata'];
            $isYearly = ($metadata['is_yearly'] ?? 'false') === 'true';
            $package = $metadata['to_package'] ?? $metadata['package'] ?? 'starter';

            $subscription = $billing->subscription();

            // End trial period if the subscription is still on trial
            // This transitions the subscription from 'trialing' to 'active' status
            if ($subscription && $subscription->onTrial()) {
                $subscription->endTrial();
                Log::info('Trial ended after first payment', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                ]);
            }

            // Grant subscription quotas and credits
            app(GrantSubscriptionAction::class)->execute($user, $package, $isYearly);

            // Clean up the metadata if this was a scheduled change
            if ($subscription && isset($metadata['pending_change']) && $metadata['pending_change'] === 'true') {
                $subscription->updateStripeSubscription([
                    'metadata' => [
                        'pending_change' => 'false',
                        'applied_at' => now()->toISOString(),
                        'from_package' => null,
                        'to_package' => null,
                        'new_price_id' => null,
                        'is_yearly' => null,
                        'scheduled_at' => null,
                        'effective_date' => null,
                    ],
                ]);
            }

            // Refresh user from database to ensure we have latest credit data
            $user->refresh();
            $user->creditSystem()->refreshCanSendMessages();
        }
    }

    private function handleTrialRegistration(WebhookReceived $event): void
    {
        $data = $event->payload['data']['object'];
        $metadata = $data['metadata'] ?? [];

        $user = $this->resolveOrCreateTrialUser($data, $metadata);

        if (! $user) {
            return;
        }

        // Populate Cashier's subscriptions/subscription_items for this checkout.
        // Cashier's own customer.subscription.created handler silently no-ops
        // when it fires before this checkout has linked the Stripe customer to
        // a UserBilling — and Stripe doesn't guarantee webhook ordering. Syncing
        // here makes subscribed(), plan-from-stripe_price, and trial/cancellation
        // tracking work regardless of delivery order.
        $this->syncSubscriptionFromStripe($user, $data['subscription'] ?? null);

        // grantSubscription() refreshes the user and credit cache internally.
        $this->grantSubscriptionFromMetadata($user, $metadata);
    }

    /**
     * Resolve the User this trial checkout belongs to, creating the account
     * (and default team + welcome email) when it's a brand-new customer.
     *
     * Returns null only when account creation failed — already reported.
     */
    private function resolveOrCreateTrialUser(array $data, array $metadata): ?User
    {
        // Returning customer already linked by an earlier checkout.
        $existingUser = $this->userForCustomer($data);

        if ($existingUser) {
            return $existingUser;
        }

        try {
            $customer = Cashier::stripe()->customers->retrieve($data['customer']);

            $email = $customer->email;
            $name = $customer->name ?? explode('@', $email)[0];

            // Existing account paying under a new Stripe customer — relink it.
            $existingUserByEmail = User::query()->where('email', $email)->first();

            if ($existingUserByEmail) {
                $existingUserByEmail->billing()->updateOrCreate([], ['stripe_id' => $data['customer']]);

                return $existingUserByEmail;
            }

            $password = Str::random();

            $countryCode = isset($customer->address->country)
                ? strtoupper($customer->address->country)
                : null;

            /** @var User $user */
            $user = DB::transaction(function () use ($name, $email, $password, $countryCode, $metadata, $data): User {
                // `type` must be set explicitly: the UserObserver only auto-provisions
                // the UserBilling row (and starter quota defaults) for Workspace/Admin
                // users, and it reads $user->type at `created` time — before the DB
                // column default is reflected back into the model — so an omitted type
                // reads as null and the billing row is silently skipped, leaving the
                // Stripe customer unlinked.
                /** @var User $user */
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'type' => UserType::Workspace,
                    'password' => Hash::make($password),
                    'domain' => parse_url(config('app.url'), PHP_URL_HOST),
                    'registered_ip' => $metadata['user_ip'] ?? null,
                    'registered_country_code' => $countryCode,
                    'email_verified_at' => now(),
                ]);

                $user->billing()->updateOrCreate([], ['stripe_id' => $data['customer']]);

                app(TeamService::class)->createDefaultTeam($user);

                return $user;
            });

            // The random password above is never shown to anyone. The flag
            // makes RequirePasswordSetup walk the owner through choosing a
            // real one as soon as they land back in the app.
            $this->passwordSetup->flag($user);

            // Welcome email with login instructions — no password in it.
            // Sent after commit so a rollback can't email an account that
            // doesn't exist.
            $package = $metadata['package'] ?? 'starter';
            $user->notify(new TrialWelcomeNotification($package));

            return $user;
        } catch (Exception $e) {
            report($e);

            return null;
        }
    }

    /**
     * Create/refresh the local Cashier subscription (+ items) from a Stripe
     * subscription id. Mirrors Cashier's handleCustomerSubscriptionCreated but
     * is keyed by stripe_id via updateOrCreate, so it's idempotent even when
     * Cashier's webhook handler also processes the same subscription.
     */
    private function syncSubscriptionFromStripe(User $user, ?string $subscriptionId): void
    {
        if (! $subscriptionId) {
            return;
        }

        $billing = $user->billing()->first();

        if (! $billing) {
            return;
        }

        try {
            $stripeSubscription = Cashier::stripe()->subscriptions->retrieve($subscriptionId, [
                'expand' => ['items.data.price'],
            ]);
        } catch (Exception $e) {
            report($e);

            return;
        }

        $items = $stripeSubscription->items->data;
        $isSinglePrice = count($items) === 1;
        $firstItem = $items[0] ?? null;

        DB::transaction(function () use ($billing, $stripeSubscription, $items, $isSinglePrice, $firstItem): void {
            /** @var Subscription $subscription */
            $subscription = $billing->subscriptions()->updateOrCreate(
                ['stripe_id' => $stripeSubscription->id],
                [
                    'type' => $stripeSubscription->metadata['type'] ?? 'default',
                    'stripe_status' => $stripeSubscription->status,
                    'stripe_price' => $isSinglePrice ? $firstItem?->price->id : null,
                    'quantity' => $isSinglePrice ? ($firstItem?->quantity ?? null) : null,
                    'trial_ends_at' => $stripeSubscription->trial_end
                        ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
                        : null,
                    'ends_at' => null,
                ]
            );

            foreach ($items as $item) {
                $subscription->items()->updateOrCreate(
                    ['stripe_id' => $item->id],
                    [
                        'stripe_product' => $item->price->product,
                        'stripe_price' => $item->price->id,
                        'quantity' => $item->quantity ?? null,
                    ]
                );
            }
        });
    }

    /**
     * Helper to extract package and billing cycle from metadata and grant subscription.
     */
    private function grantSubscriptionFromMetadata(User $user, array $metadata): void
    {
        $package = $metadata['package'] ?? 'starter';
        $isYearly = ($metadata['is_yearly'] ?? 'false') === 'true';

        app(GrantSubscriptionAction::class)->execute($user, $package, $isYearly);
    }

    private function handleAddOns(array $data): void
    {
        $metadata = $data['metadata'];

        $user = $this->userForCustomer($data);

        if (! $user) {
            return;
        }

        $id = $metadata['id'] ?? null;

        // Auto-recharge addons are created synchronously by
        // AutoRechargeCreditsAction (since it charges off-session and does
        // not go through Checkout). Only handle the on-session Checkout flow
        // here.
        if ($id === 'extra_message_credits') {
            $user->addons()->create([
                'type' => $id,
                'amount' => $metadata['messages'],
                'stripe_id' => $data['id'],
                'started_at' => now(),
                'ends_at' => now()->addMonth(),
            ]);

            // Refresh user from database to ensure we have latest addon data
            $user->refresh();
            $user->creditSystem()->refreshCanSendMessages();
        }
    }

    /**
     * checkout.session.completed for the `addon_subscription` family —
     * extra agents and custom domains. Mirrors the subscription quantity
     * onto `user_quotas` so the rest of the app can keep reading from one
     * place.
     */
    private function handleAddonSubscriptionCreated(array $data): void
    {
        $this->syncAddonQuota($data, (int) ($data['metadata']['quantity'] ?? 1));
    }

    /**
     * customer.subscription.updated — keep quotas in sync when a user
     * changes the quantity of an addon subscription from the Stripe portal
     * or via the API.
     */
    private function handleAddonSubscriptionUpdated(array $data): void
    {
        if (($data['metadata']['type'] ?? null) !== 'addon_subscription') {
            return;
        }

        $this->syncAddonQuota($data, (int) ($data['quantity'] ?? $data['metadata']['quantity'] ?? 0));
    }

    /**
     * customer.subscription.deleted — reset the relevant quota column when
     * an addon subscription is cancelled (immediately or at period end).
     */
    private function handleAddonSubscriptionDeleted(array $data): void
    {
        if (($data['metadata']['type'] ?? null) !== 'addon_subscription') {
            return;
        }

        $this->syncAddonQuota($data, 0);
    }

    /**
     * Resolve the workspace owner behind a Stripe customer id. Returns null
     * when no billing row links to that customer (e.g. an event for a customer
     * we never provisioned) so callers can bail quietly.
     */
    private function userForCustomer(array $data): ?User
    {
        return UserBilling::query()->where('stripe_id', $data['customer'])->first()?->user;
    }

    /**
     * Mirror an `addon_subscription` quantity onto the matching `user_quotas`
     * column. Shared by the created / updated / deleted handlers — only the
     * quantity differs (deleted passes 0).
     */
    private function syncAddonQuota(array $data, int $quantity): void
    {
        $user = $this->userForCustomer($data);

        if (! $user) {
            return;
        }

        $addon = Addon::tryFrom($data['metadata']['addon'] ?? '');

        if (! $addon) {
            return;
        }

        $quotaColumn = $addon->quotaColumn();

        // Subscription-only addons (e.g. remove_branding) keep no quota mirror;
        // their entitlement is read live from the Cashier subscription row that
        // Cashier itself syncs. Nothing to write here.
        if ($quotaColumn === null) {
            return;
        }

        $user->quota()->updateOrCreate([], [$quotaColumn => $quantity]);
    }
}
