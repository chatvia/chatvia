<?php

namespace App\Listeners;

use App\Models\Member;
use App\Models\User;
use App\Models\UserQuota;
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
            $this->handleSubscription($data);

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

    private function handleSubscription(array $data): void
    {
        $user = User::query()->where('stripe_id', $data['customer'])->first();

        $metadata = $data['subscription_details']['metadata'];

        // Stripe metadata only supports string values. This is a limitation of Stripe's API.
        $isYearly = $metadata['is_yearly'] === 'true';
        $toPackage = $metadata['to_package'];
        $fromPackage = $metadata['from_package'];

        $cycle = $isYearly ? 'yearly' : 'monthly';

        $quotaLimit = config('billing.packages.'.$toPackage.'.quota_limits.'.$cycle);
        $fixedLimit = config('billing.packages.'.$toPackage.'.quota_limits.fixed');

        $endsAt = $isYearly ? now()->addYear() : now()->addMonth();

        /** @var UserQuota $quota */
        $quota = $user->quota;

        $user->quota()->updateOrCreate(
            [],
            [
                'agents_limit' => ($quota->agents_limit ?? 0) + $fixedLimit['agents_limit'],
                'pages_limit' => ($quota->pages_limit ?? 0) + $fixedLimit['pages_limit'],
                'minutes_limit' => ($quota->minutes_limit ?? 0) + $fixedLimit['minutes_limit'],
                'custom_domains_limit' => ($quota->custom_domains_limit ?? 0) + $fixedLimit['custom_domains_limit'],
                'members_limit' => ($quota->members_limit ?? 0) + $fixedLimit['members_limit'],
                'links_limit' => ($quota->links_limit ?? 0) + $fixedLimit['links_limit'],
            ]
        );

        $user->addCredit(
            $quotaLimit,
            $endsAt
        );

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
        // Check if user already exists
        $existingUser = User::query()->where('stripe_id', $data['customer'])->first();

        if ($existingUser) {
            // Set up quotas for existing user
            $this->setupTrialQuotas($existingUser, $data['metadata']);

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
                // Update existing user with Stripe customer ID
                $existingUserByEmail->update([
                    'stripe_id' => $data['customer'],
                ]);

                // Set up quotas based on the package
                $this->setupTrialQuotas($existingUserByEmail, $data['metadata']);

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
                'registered_ip' => $userIp, // Use the IP captured at checkout
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

            // Set up quotas based on the package
            $this->setupTrialQuotas($user, $data['metadata']);

            // Store the user ID in session metadata for auto-login after checkout
            // This will be handled by the TrialSuccessController

            // Send welcome email with login instructions and temporary password
            $package = $data['metadata']['package'] ?? 'starter';
            $user->notify(new TrialWelcomeNotification($password, $package));

        } catch (\Exception $e) {
            \Log::error('Failed to create user from trial registration', [
                'error' => $e->getMessage(),
                'customer_id' => $data['customer'] ?? null,
            ]);
        }
    }

    private function setupTrialQuotas(User $user, array $metadata): void
    {
        $package = $metadata['package'] ?? 'starter';
        $isYearly = ($metadata['is_yearly'] ?? 'false') === 'true';

        $cycle = $isYearly ? 'yearly' : 'monthly';

        $quotaLimit = config('billing.packages.'.$package.'.quota_limits.'.$cycle);
        $fixedLimit = config('billing.packages.'.$package.'.quota_limits.fixed');

        if (! $quotaLimit || ! $fixedLimit) {
            return;
        }

        $endsAt = $isYearly ? now()->addYear() : now()->addMonth();

        // Create or update quota
        $user->quota()->updateOrCreate(
            [],
            [
                'agents_limit' => $fixedLimit['agents_limit'],
                'pages_limit' => $fixedLimit['pages_limit'],
                'minutes_limit' => $fixedLimit['minutes_limit'],
                'custom_domains_limit' => $fixedLimit['custom_domains_limit'],
                'members_limit' => $fixedLimit['members_limit'],
                'links_limit' => $fixedLimit['links_limit'],
            ]
        );

        // Add message credits
        $user->addCredit(
            $quotaLimit,
            $endsAt
        );
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
