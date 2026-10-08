<?php

namespace App\Identity\Workspace\Observers;

use App\Identity\Workspace\Models\User;

/**
 * Keeps the Stripe customer in step with the user's name and email.
 *
 * Billing, quota and the default team are not created here: the trial
 * checkout's Stripe webhook provisions them (with billing turned off, the
 * sign-up form creates the default team and nothing else is needed).
 */
class UserObserver
{
    public function updated(User $user): void
    {
        // Cashier mirrors only the user's name + email to Stripe (see
        // UserBilling::stripeName/stripeEmail). Without this guard every
        // `users` UPDATE — including the `current_team_id` write from
        // User::bootstrapCurrentTeam() — fires a synchronous Stripe API
        // call, so a plain page render can round-trip to Stripe.
        if (! $user->wasChanged(['name', 'email'])) {
            return;
        }

        $billing = $user->billing;

        if ($billing?->hasStripeId()) {
            $billing->syncStripeCustomerDetails();
        }
    }

    // There is deliberately no `deleting`/`deleted` hook that cancels Stripe
    // subscriptions: DeleteUserAccountAction::cancelSubscriptions() owns that and
    // runs outside the delete transaction. A second cancel here would hit a
    // subscription Stripe has already closed, which Stripe rejects. Any other
    // delete path (e.g. a staff admin tool) must go through that action.
}
