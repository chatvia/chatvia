<?php

namespace Domain\Billing\Data\Stripe;

/**
 * A finished Stripe Checkout Session (`checkout.session.completed`): who paid,
 * the subscription it opened (for subscription-mode checkouts) and the
 * metadata we attached when we created it.
 */
final readonly class CompletedCheckoutData
{
    public function __construct(
        public string $sessionId,
        public string $customerId,
        public ?string $subscriptionId,
        public StripeMetadata $metadata,
    ) {}

    public static function fromEvent(StripeEventData $event): self
    {
        $subscriptionId = $event->object['subscription'] ?? null;

        return new self(
            sessionId: (string) ($event->object['id'] ?? ''),
            customerId: (string) ($event->object['customer'] ?? ''),
            subscriptionId: is_string($subscriptionId) && $subscriptionId !== '' ? $subscriptionId : null,
            metadata: $event->metadata(),
        );
    }
}
