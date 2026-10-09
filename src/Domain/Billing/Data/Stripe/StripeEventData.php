<?php

namespace Domain\Billing\Data\Stripe;

/**
 * One Stripe webhook event: its id (for idempotency), its type, and the object
 * it is about. Built once from Cashier's decoded payload so the handlers below
 * the webhook listener never index into the raw array.
 */
final readonly class StripeEventData
{
    public const string CHECKOUT_COMPLETED = 'checkout.session.completed';

    public const string INVOICE_PAID = 'invoice.payment_succeeded';

    /**
     * @param  array<string, mixed>  $object
     */
    public function __construct(
        public ?string $id,
        public string $type,
        public array $object,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $id = $payload['id'] ?? null;
        $object = $payload['data']['object'] ?? [];

        return new self(
            id: is_string($id) && $id !== '' ? $id : null,
            type: (string) ($payload['type'] ?? ''),
            object: is_array($object) ? $object : [],
        );
    }

    public function is(string $type): bool
    {
        return $this->type === $type;
    }

    /** The metadata of the event's object (a checkout session or an invoice). */
    public function metadata(): StripeMetadata
    {
        return StripeMetadata::from($this->object['metadata'] ?? null);
    }
}
