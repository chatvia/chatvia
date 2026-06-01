<?php

namespace App\Billing\Data\Resources\Subscription;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BillingPendingChangeData extends Data
{
    public function __construct(
        public ?string $from_package,
        public ?string $to_package,
        public ?bool $is_yearly,
        public ?int $effective_date,
        public ?string $scheduled_at,
        public bool $has_pending_update = false,
    ) {}

    public static function fromMetadata(object $stripeSubscription): ?self
    {
        $metadata = $stripeSubscription->metadata;

        // Check metadata for pending changes
        if (isset($metadata->pending_change) && $metadata->pending_change === 'true') {
            return new self(
                from_package: $metadata->from_package ?? null,
                to_package: $metadata->to_package ?? null,
                is_yearly: ($metadata->is_yearly ?? 'false') === 'true',
                effective_date: $stripeSubscription->current_period_end * 1000,
                scheduled_at: $metadata->scheduled_at ?? null,
                has_pending_update: false,
            );
        }

        // Check for pending updates on subscription items
        foreach ($stripeSubscription->items->data as $item) {
            if (isset($item->pending_update) && $item->pending_update) {
                return new self(
                    from_package: null,
                    to_package: null,
                    is_yearly: null,
                    effective_date: $stripeSubscription->current_period_end * 1000,
                    scheduled_at: null,
                    has_pending_update: true,
                );
            }
        }

        return null;
    }
}
