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
            // Handle the incoming event...
            $user = User::query()->where('stripe_id', $data['customer'])->first();

            $metadata = $data['subscription_details']['metadata'];

            $isYearly = (bool) $metadata['is_yearly'];
            $package = $metadata['package'];

            $period = $isYearly ? 'yearly' : 'monthly';

            $fromPackage = $metadata['from_package'];
            $toPackage = $metadata['to_package'];

            $quotaLimit = config('billing.packages.'.$package.'.quota_limits.'.$period);

            $user->quotas()->create([

            ]);
        }
    }
}
