<?php

namespace App\Listeners;

use App\Models\Member;
use App\Models\User;
use App\Notifications\TrialWelcomeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    public function handle(WebhookReceived $event): void
    {
        $data = $event->payload['data']['object'];

        if ($event->payload['type'] === 'invoice.payment_succeeded' && isset($data['subscription_details']['metadata'])) {
            $this->handleInvoicePaymentSucceeded($data);

            return;
        }

        if ($event->payload['type'] === 'checkout.session.completed' && isset($data['metadata']['registration_type'])) {
            $metadata = $data['metadata'];

            if ($metadata['registration_type'] === 'trial_registration') {
                $this->handleTrialRegistration($data);

                return;
            }
        }

        // Handle addon purchases (using 'type' field for addons is fine since they don't create subscriptions)
        if ($event->payload['type'] === 'checkout.session.completed' && isset($data['metadata']['type']) && $data['metadata']['type'] === 'addon') {
            $this->handleAddOns($data);
        }
    }

    /**
     * Handle invoice.payment_succeeded event (subscription renewals).
     * Only grants credits for actual renewals (subscription_cycle), not initial charges.
     * Initial charges from trial conversions are handled by handleTrialRegistration.
     */
    private function handleInvoicePaymentSucceeded(array $data): void
    {
        // Only process subscription renewals, not initial subscription creation
        // This prevents double credits when trial converts to paid subscription
        $billingReason = $data['billing_reason'] ?? null;

        if ($billingReason !== 'subscription_cycle') {
            return;
        }

        $user = User::query()->where('stripe_id', $data['customer'])->first();

        if (! $user) {
            return;
        }

        $metadata = $data['subscription_details']['metadata'];
        $isYearly = ($metadata['is_yearly'] ?? 'false') === 'true';
        $package = $metadata['to_package'] ?? $metadata['package'] ?? 'starter';

        $this->grantSubscription($user, $package, $isYearly);

        // Clean up the metadata if this was a scheduled change
        if (isset($metadata['pending_change']) && $metadata['pending_change'] === 'true') {
            $subscription = $user->subscription();
            $subscription?->updateStripeSubscription([
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
    }

    private function handleTrialRegistration(array $data): void
    {
        // Check if user already exists by stripe_id
        $existingUser = User::query()->where('stripe_id', $data['customer'])->first();

        if ($existingUser) {
            $this->grantSubscriptionFromMetadata($existingUser, $data['metadata']);

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
                $this->grantSubscriptionFromMetadata($existingUserByEmail, $data['metadata']);

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
            $userIp = $data['metadata']['user_ip'] ?? null;

            // Create new user
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

            // Create member record
            Member::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_owner' => true,
            ]);

            // Grant subscription quotas
            $this->grantSubscriptionFromMetadata($user, $data['metadata']);

            // Send welcome email with login instructions and temporary password
            $package = $data['metadata']['package'] ?? 'starter';
            $user->notify(new TrialWelcomeNotification($password, $package));

        } catch (\Exception $e) {
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
            \Log::warning('Invalid package configuration', ['package' => $package, 'cycle' => $cycle]);

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
        ]);

        // Add message credits for the billing period
        $user->addCredit($quotaLimit, $endsAt);
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

            $user->creditSystem()->refreshAvailableMessages();
        }
    }
}
