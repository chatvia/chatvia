<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires when a user's available message credits cross the auto-recharge
 * threshold (configured on `user_billing`). Carries only the user id; the
 * listener re-fetches the billing row to read fresh threshold/amount
 * settings before charging.
 */
class MessageCreditsLow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $availableMessages,
    ) {}
}
