<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * The `registration_type` metadata value on a Stripe Checkout Session. It tells
 * the webhook that a completed checkout should create (or find) the account
 * that pays for it, rather than change an existing subscription.
 */
enum RegistrationType: string
{
    case TrialRegistration = 'trial_registration';
}
