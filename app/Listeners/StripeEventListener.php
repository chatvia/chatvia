<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\TrialWelcomeNotification;
use App\Services\Teams\TeamService;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    public function handle(WebhookReceived $event): void
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

        // Log customer.subscription.updated events specifically
        if ($type === 'customer.subscription.updated') {
            Log::info('Subscription status updated via webhook', [
                'subscription_id' => $data['id'] ?? null,
                'status' => $data['status'] ?? null,
                'customer' => $data['customer'] ?? null,
            ]);
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

        $user = User::query()->where('stripe_id', $data['customer'])->first();

        if (! $user) {
            Log::warning('User not found for invoice payment', ['customer' => $data['customer']]);

            return;
        }

        // Handle subscription renewals
        if ($billingReason === 'subscription_cycle') {
            $metadata = $data['subscription_details']['metadata'];
            $isYearly = ($metadata['is_yearly'] ?? 'false') === 'true';
            $package = $metadata['to_package'] ?? $metadata['package'] ?? 'starter';

            $subscription = $user->subscription();

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
            $this->grantSubscription($user, $package, $isYearly);

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

        // Get metadata from checkout.session.completed event
        $metadata = $data['metadata'] ?? [];

        // Check if user already exists by stripe_id
        $existingUser = User::query()->where('stripe_id', $data['customer'])->first();

        if ($existingUser) {
            $this->grantSubscriptionFromMetadata($existingUser, $metadata);

            return;
        }

        try {
            // Retrieve customer details using Cashier
            $customer = Cashier::stripe()->customers->retrieve($data['customer']);

            $email = $customer->email;
            $name = $customer->name ?? explode('@', $email)[0];

            // Check if user with this email already exists
            $existingUserByEmail = User::query()->where('email', $email)->first();

            if ($existingUserByEmail) {
                $existingUserByEmail->update(['stripe_id' => $data['customer']]);
                $this->grantSubscriptionFromMetadata($existingUserByEmail, $metadata);

                return;
            }

            // Generate a random password
            $password = Str::random(16);

            // Get country code from Stripe customer address if available
            $countryCode = null;
            if (isset($customer->address->country)) {
                $countryCode = strtoupper($customer->address->country);
            }

            // Get user IP from metadata (captured at checkout)
            $userIp = $metadata['user_ip'] ?? null;

            // Create new user
            /** @var User $user */
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'stripe_id' => $data['customer'],
                'domain' => parse_url(config('app.url'), PHP_URL_HOST),
                'registered_ip' => $userIp,
                'registered_country_code' => $countryCode,
                'email_verified_at' => now(),
            ]);

            app(TeamService::class)->createDefaultTeam($user);

            // Grant subscription quotas
            $this->grantSubscriptionFromMetadata($user, $metadata);

            // Refresh user from database to ensure we have latest credit data
            $user->refresh();

            $user->creditSystem()->refreshCanSendMessages();

            // Send welcome email with login instructions and temporary password
            $package = $metadata['package'] ?? 'starter';
            $user->notify(new TrialWelcomeNotification($password, $package));

        } catch (Exception $e) {
            report($e);
        }
    }

    /**
     * Helper to extract package and billing cycle from metadata and grant subscription.
     */
    private function grantSubscriptionFromMetadata(User $user, array $metadata): void
    {
        $package = $metadata['package'] ?? 'starter';
        $isYearly = ($metadata['is_yearly'] ?? 'false') === 'true';

        $this->grantSubscription($user, $package, $isYearly);
    }

    /**
     * Grant subscription quotas and credits to a user.
     * Used for both new subscriptions (trials) and renewals.
     */
    private function grantSubscription(User $user, string $package, bool $isYearly): void
    {
        $cycle = $isYearly ? 'yearly' : 'monthly';

        $quotaLimit = config('billing.packages.'.$package.'.quota_limits.'.$cycle);
        $fixedLimit = config('billing.packages.'.$package.'.quota_limits.fixed');

        if (! $quotaLimit || ! $fixedLimit) {
            Log::warning('Invalid package configuration', ['package' => $package, 'cycle' => $cycle]);

            return;
        }

        $endsAt = $isYearly ? now()->addYear() : now()->addMonth();

        // Set quota limits (fixed per package, not cumulative)
        $user->quota()->updateOrCreate([], [
            'agents_limit' => $fixedLimit['agents_limit'],
            'pages_limit' => $fixedLimit['pages_limit'],
            'minutes_limit' => $fixedLimit['minutes_limit'],
            'custom_domains_limit' => $fixedLimit['custom_domains_limit'],
            'members_limit' => $fixedLimit['members_limit'],
            'links_limit' => $fixedLimit['links_limit'],
            'teams_limit' => $fixedLimit['teams_limit'],
        ]);

        // Add message credits for the billing period
        $user->addCredit($quotaLimit, $endsAt);

        $user->refresh();

        $user->creditSystem()->refreshCanSendMessages();
    }

    private function handleAddOns(array $data): void
    {
        $metadata = $data['metadata'];

        $user = User::query()->where('stripe_id', $data['customer'])->first();

        if (! $user) {
            return;
        }

        $id = $metadata['id'] ?? null;

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
}
