<?php

namespace App\Listeners;

use App\Models\User;
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    public function handle(WebhookReceived $event): void
    {
        $data = $event->payload['data']['object'];

        if ($event->payload['type'] === 'invoice.payment_succeeded') {
            $user = User::query()->where('stripe_id', $data['customer'])->first();

            $metadata = $data['subscription_details']['metadata'];

            $isYearly = (bool) $metadata['is_yearly'];
            $toPackage = $metadata['to_package'];

            $cycle = $isYearly ? 'yearly' : 'monthly';

            //            $fromPackage = $metadata['from_package'];
            //            $toPackage = $metadata['to_package'];

            $quotaLimit = config('billing.packages.'.$toPackage.'.quota_limits.'.$cycle);

            // Update Quota usage...
            $user->quotas()->create([
                'pages_limit' => $quotaLimit['pages_limit'],
                'minutes_limit' => $quotaLimit['minutes_limit'],
                'messages_limit' => $quotaLimit['messages_limit'],
                'agents_instruction_limit' => $quotaLimit['agents_instruction_limit'],
                'agents_knowledge_limit' => $quotaLimit['agents_knowledge_limit'],
                'billing_cycle' => $cycle,
                'is_paid_plan' => true,
                'billing_period_starts_at' => now(),
                'billing_period_ends_at' => $isYearly ? now()->addYear() : now()->addMonth(),
            ]);
        }
    }
}
