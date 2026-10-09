<?php

namespace Domain\Billing\Data\Stripe;

/**
 * A paid Stripe invoice (`invoice.payment_succeeded`), reduced to what the
 * renewal flow needs.
 *
 * Stripe renders the invoice in the webhook endpoint's API version, so two
 * shapes are read: older versions put the subscription details at
 * `subscription_details` and the line price at `price.id`; newer ones use
 * `parent.subscription_details` and `pricing.price_details.price`.
 */
final readonly class PaidInvoiceData
{
    /** Stripe's billing reason for the first invoice of a new subscription. */
    public const string REASON_SUBSCRIPTION_CREATE = 'subscription_create';

    /** Stripe's billing reason for a renewal at the end of a billing period. */
    public const string REASON_SUBSCRIPTION_CYCLE = 'subscription_cycle';

    /**
     * @param  list<string>  $linePriceIds  prices of the recurring (non-proration) lines
     */
    public function __construct(
        public ?string $invoiceId,
        public ?string $customerId,
        public ?string $billingReason,
        public ?string $subscriptionId,
        public ?StripeMetadata $subscriptionMetadata,
        public array $linePriceIds,
    ) {}

    /**
     * @param  array<string, mixed>  $invoice
     */
    public static function fromInvoice(array $invoice): self
    {
        $details = $invoice['subscription_details'] ?? $invoice['parent']['subscription_details'] ?? null;
        $subscriptionId = $invoice['subscription'] ?? $invoice['parent']['subscription_details']['subscription'] ?? null;

        return new self(
            invoiceId: self::nonEmptyString($invoice['id'] ?? null),
            customerId: self::nonEmptyString($invoice['customer'] ?? null),
            billingReason: self::nonEmptyString($invoice['billing_reason'] ?? null),
            subscriptionId: self::nonEmptyString($subscriptionId),
            subscriptionMetadata: is_array($details) && isset($details['metadata'])
                ? StripeMetadata::from((array) $details['metadata'])
                : null,
            linePriceIds: self::recurringLinePriceIds($invoice),
        );
    }

    /** Whether the invoice belongs to a subscription (and not a one-off charge). */
    public function isForSubscription(): bool
    {
        return $this->subscriptionMetadata instanceof StripeMetadata;
    }

    public function opensSubscription(): bool
    {
        return $this->billingReason === self::REASON_SUBSCRIPTION_CREATE;
    }

    public function renewsSubscription(): bool
    {
        return $this->billingReason === self::REASON_SUBSCRIPTION_CYCLE;
    }

    /**
     * Proration lines are skipped because they also carry the previous plan's
     * price.
     *
     * @param  array<string, mixed>  $invoice
     * @return list<string>
     */
    private static function recurringLinePriceIds(array $invoice): array
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
            $priceId = self::nonEmptyString(is_array($price) ? ($price['id'] ?? null) : $price);

            if ($priceId !== null) {
                $priceIds[] = $priceId;
            }
        }

        return $priceIds;
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
