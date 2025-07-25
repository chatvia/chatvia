<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Billing\Credit;
use App\Services\Billing\UserCreditSystem;
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

        if ($event->payload['type'] === 'checkout.session.completed' && isset($data['metadata']['type'])) {
            $this->handleAddOns($data);

            return;
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
        $agentsLimit = config('billing.packages.'.$toPackage.'.quota_limits.fixed');

        $endsAt = $isYearly ? now()->addYear() : now()->addMonth();

        $user->update([
            'agents_knowledge_limit' => $agentsLimit['agents_knowledge_limit'],
            'agents_instruction_limit' => $agentsLimit['agents_instruction_limit'],
        ]);

        app(UserCreditSystem::class)->setUserId($user->id)->addCreditForSubscription(
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
                ]
            ]);
        }
    }

    private function handleAddOns(array $data): void
    {
        $metadata = $data['metadata'];

        $user = User::query()->where('stripe_id', $data['customer'])->first();

        if ($metadata['type'] === 'addon') {
            $id = $metadata['id'];

            if ($id === 'extra_message_credits') {
                $user->addons()->create([
                    'type' => $id,
                    'amount' => $metadata['messages'],
                    'stripe_id' => $data['id'],
                    'started_at' => now(),
                    'ends_at' => now()->addMonth(),
                ]);
            }
        }
    }
}
