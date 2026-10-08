<?php

namespace Domain\Billing\Enums;

use Domain\Billing\Services\MessageSendGate;

/**
 * The outcome of the per-turn send gate ({@see MessageSendGate}): either the
 * workspace may receive another assistant reply, or the reason it may not.
 * Each surface maps the reason to its own message (the web stream sends a
 * typed error event, the messaging channels send a short text).
 */
enum MessageAdmission: string
{
    case Allowed = 'allowed';
    case SubscriptionInactive = 'subscription_inactive';
    case CreditsExhausted = 'credits_exhausted';

    public function allowed(): bool
    {
        return $this === self::Allowed;
    }
}
