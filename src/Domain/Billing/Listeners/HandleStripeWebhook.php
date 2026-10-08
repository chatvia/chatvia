<?php

namespace Domain\Billing\Listeners;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Domain\Billing\Actions\Subscriptions\GrantSubscriptionAction;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Models\UserBilling;
use Domain\Billing\Notifications\FounderWelcomeNotification;
use Domain\Billing\Notifications\TrialWelcomeNotification;
use Domain\Billing\Services\Billing;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Subscription;
use RuntimeException;
use Support\Enums\User\UserType;
use Throwable;

class HandleStripeWebhook
{
    public function __construct(
        private readonly PasswordSetup $passwordSetup,
        private readonly Billing $plans,
    ) {}

    public function handle(WebhookReceived $event): void
    {
        $this->assertWebhookIsSigned();

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

    /**
     * Cashier checks the Stripe signature only when a webhook secret is set, so
     * without one every POST to the webhook route is trusted and a forged event
     * could grant plans and credits. Production already refuses to boot without
     * the secret (AppServiceProvider); this covers every other shared
     * environment (staging, demo) at request time. Cashier dispatches
     * WebhookReceived before its own handlers run, so throwing here also stops
     * Cashier from syncing subscriptions from the unsigned payload. Local
     * development and the test suite may still run unsigned.
     */
    private function assertWebhookIsSigned(): void
    {
        if (filled(config('cashier.webhook.secret')) || app()->environment('local', 'testing')) {
            return;
        }

        throw new RuntimeException(
            'STRIPE_WEBHOOK_SECRET is not set. Refusing to process an unsigned Stripe webhook.'
        );
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
        if ($type === 'invoice.payment_succeeded' && $this->invoiceSubscriptionMetadata($data) !== null) {
            $this->handleInvoicePaymentSucceeded($event);

            return;
        }

        // Handle addon purchases (using 'type' field for addons is fine since they don't create subscriptions)
        if ($type === 'checkout.session.completed' && isset($data['metadata']['type']) && $data['metadata']['type'] === 'addon') {
            $this->handleAddOns($data);
        }

        // Add-on subscription quotas (extra agents, members, custom domains) are
        // synced after Cashier has stored the subscription change — see
        // SyncAddonQuotasFromStripe, which listens to WebhookHandled.
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
            $metadata = $this->invoiceSubscriptionMetadata($data) ?? [];

            $subscription = $billing->subscription();

            $plan = $this->renewedPlan($data, $metadata, $billing, $subscription);

            // An add-on subscription (extra agents, members, ...) renews on its
            // own invoice. It must not re-grant the plan: that would reset the
            // cycle's message credits and overwrite the plan quotas.
            if ($plan === null) {
                Log::info('Skipping renewal invoice that is not for the plan subscription', [
                    'invoice_id' => $data['id'] ?? null,
                    'customer' => $data['customer'] ?? null,
                ]);

                return;
            }

            ['package' => $package, 'is_yearly' => $isYearly] = $plan;

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

    /**
     * The metadata of the subscription an invoice belongs to, or null when the
     * invoice is not for a subscription. Stripe renders the invoice in the
     * webhook endpoint's API version: older versions put the details at
     * `subscription_details`, newer ones at `parent.subscription_details`.
     *
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>|null
     */
    private function invoiceSubscriptionMetadata(array $invoice): ?array
    {
        $details = $invoice['subscription_details'] ?? $invoice['parent']['subscription_details'] ?? null;

        if (! is_array($details) || ! isset($details['metadata'])) {
            return null;
        }

        return (array) $details['metadata'];
    }

    /**
     * The plan and billing cycle a renewal invoice pays for, or null when the
     * invoice is for an add-on subscription.
     *
     * The Stripe price is the source of truth: first the invoice's own
     * subscription lines, then the locally synced Cashier subscription. The
     * subscription metadata is only a last resort for a price that is not in
     * the billing config, because the plan-change flow clears `to_package` and
     * `is_yearly` once a scheduled change has been applied.
     *
     * @param  array<string, mixed>  $invoice
     * @param  array<string, mixed>  $metadata
     * @return array{package: string, is_yearly: bool}|null
     */
    private function renewedPlan(array $invoice, array $metadata, UserBilling $billing, ?Subscription $subscription): ?array
    {
        foreach ($this->invoiceLinePriceIds($invoice) as $priceId) {
            $plan = $this->plans->planForPriceId($priceId);

            if ($plan !== null) {
                return $plan;
            }
        }

        if ($this->isAddonInvoice($invoice, $metadata, $billing)) {
            return null;
        }

        $localPriceIds = $subscription === null ? [] : [
            $subscription->stripe_price,
            ...$subscription->items()->pluck('stripe_price')->all(),
        ];

        foreach ($localPriceIds as $priceId) {
            $plan = $this->plans->planForPriceId(is_string($priceId) ? $priceId : null);

            if ($plan !== null) {
                return $plan;
            }
        }

        $package = $metadata['to_package'] ?? $metadata['package'] ?? $billing->plan ?? config('billing.default_package', 'starter');

        Log::warning('Stripe renewal: no configured plan price found, using the subscription metadata', [
            'invoice_id' => $invoice['id'] ?? null,
            'customer' => $invoice['customer'] ?? null,
            'package' => $package,
        ]);

        return [
            'package' => (string) $package,
            'is_yearly' => ($metadata['is_yearly'] ?? 'false') === 'true',
        ];
    }

    /**
     * Price ids of the invoice's recurring subscription lines. Proration lines
     * are skipped because they also carry the previous plan's price. Reads both
     * line shapes: `price.id` (older API versions) and
     * `pricing.price_details.price` (newer ones).
     *
     * @param  array<string, mixed>  $invoice
     * @return list<string>
     */
    private function invoiceLinePriceIds(array $invoice): array
    {
        $priceIds = [];

        foreach ((array) ($invoice['lines']['data'] ?? []) as $line) {
            if (! is_array($line)) {
                continue;
            }

            if ((bool) ($line['proration'] ?? $line['parent']['subscription_item_details']['proration'] ?? false)) {
                continue;
            }

            $price = $line['price'] ?? $line['pricing']['price_details']['price'] ?? null;
            $priceId = is_array($price) ? ($price['id'] ?? null) : $price;

            if (is_string($priceId) && $priceId !== '') {
                $priceIds[] = $priceId;
            }
        }

        return $priceIds;
    }

    /**
     * Whether a renewal invoice belongs to an add-on subscription rather than
     * the plan: the add-on checkout tags its subscription with an `addon`
     * metadata key, and every add-on gets its own (non-default) Cashier
     * subscription row.
     *
     * @param  array<string, mixed>  $invoice
     * @param  array<string, mixed>  $metadata
     */
    private function isAddonInvoice(array $invoice, array $metadata, UserBilling $billing): bool
    {
        if (isset($metadata['addon']) || ($metadata['type'] ?? null) === 'addon_subscription') {
            return true;
        }

        $subscriptionId = $invoice['subscription'] ?? $invoice['parent']['subscription_details']['subscription'] ?? null;

        if (! is_string($subscriptionId) || $subscriptionId === '') {
            return false;
        }

        return $billing->subscriptions()
            ->where('stripe_id', $subscriptionId)
            ->where('type', '!=', 'default')
            ->exists();
    }

    private function handleTrialRegistration(WebhookReceived $event): void
    {
        $data = $event->payload['data']['object'];
        $metadata = $data['metadata'] ?? [];

        $user = $this->resolveOrCreateTrialUser($data, $metadata);

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
     * A failure here (Stripe or the database) is NOT swallowed: it propagates
     * so handle() releases the idempotency claim and the webhook answers with
     * an error, which makes Stripe retry. Swallowing it would answer 200 and
     * the paying customer would never get an account. A retry after the
     * account was created finds it through the linked Stripe customer.
     */
    private function resolveOrCreateTrialUser(array $data, array $metadata): User
    {
        // Returning customer already linked by an earlier checkout.
        $existingUser = $this->userForCustomer($data);

        if ($existingUser) {
            return $existingUser;
        }

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

        // The account now exists, and a Stripe retry would find it and skip
        // this branch, so a mail failure must not fail the webhook: report it
        // and let the subscription grant below continue.
        try {
            // Welcome email with login instructions — no password in it.
            // Sent after commit so a rollback can't email an account that
            // doesn't exist.
            $package = $metadata['package'] ?? 'starter';
            $user->notify(new TrialWelcomeNotification($package));

            // A personal note from the founder, sent only to brand-new accounts
            // (this branch) — returning customers above don't get it again.
            $user->notify(new FounderWelcomeNotification);
        } catch (Throwable $e) {
            report($e);
        }

        return $user;
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

        // A Stripe failure propagates (see resolveOrCreateTrialUser): without
        // the local subscription row the account is not subscribed, so the
        // event must be retried rather than answered with 200.
        $stripeSubscription = Cashier::stripe()->subscriptions->retrieve($subscriptionId, [
            'expand' => ['items.data.price'],
        ]);

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
            // Keyed on the Checkout Session id so a redelivered event (after the
            // idempotency claim expired, or on a retry after a later failure)
            // can't grant the same purchase twice or extend its expiry.
            $user->addons()->firstOrCreate(
                ['stripe_id' => $data['id'], 'type' => $id],
                [
                    'amount' => $metadata['messages'],
                    'started_at' => now(),
                    'ends_at' => now()->addMonth(),
                ],
            );

            // Refresh user from database to ensure we have latest addon data
            $user->refresh();
            $user->creditSystem()->refreshCanSendMessages();
        }
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
}
