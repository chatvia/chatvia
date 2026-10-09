<?php

namespace Domain\Billing\Exceptions;

use RuntimeException;

/**
 * A guest trial checkout was paid with the e-mail address of an account that
 * already belongs to another Stripe customer.
 *
 * Stripe Checkout lets anyone type any e-mail address, so the checkout is not
 * proof that the person owns that account. The account is left untouched (no
 * relinking, no plan change) and this exception is reported so the operator can
 * refund or follow up. Only the Stripe ids are included — never the e-mail.
 */
final class TrialCheckoutEmailInUseException extends RuntimeException
{
    public static function forCheckout(string $checkoutSessionId, string $customerId): self
    {
        return new self(sprintf(
            'Trial checkout %s (customer %s) used the e-mail of an account linked to another Stripe customer; the account was not changed.',
            $checkoutSessionId,
            $customerId,
        ));
    }
}
