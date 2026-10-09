<?php

namespace Domain\Billing\Listeners;

use Domain\Billing\Events\TrialWorkspaceProvisioned;
use Domain\Billing\Notifications\FounderWelcomeNotification;
use Domain\Billing\Notifications\TrialWelcomeNotification;
use Throwable;

/**
 * Welcomes a brand-new trial account: the sign-in instructions (never a
 * password) and, when configured, a personal note from the founder.
 *
 * Runs inside the Stripe webhook. The account already exists, and a Stripe
 * retry would find it and skip the welcome, so a mail failure is reported and
 * swallowed instead of failing the webhook.
 */
class SendTrialWelcomeEmails
{
    public function handle(TrialWorkspaceProvisioned $event): void
    {
        try {
            $event->user->notify(new TrialWelcomeNotification($event->package));
            $event->user->notify(new FounderWelcomeNotification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
