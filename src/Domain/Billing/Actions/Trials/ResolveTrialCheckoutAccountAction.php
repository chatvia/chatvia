<?php

namespace Domain\Billing\Actions\Trials;

use App\Identity\Workspace\Services\PasswordSetup;
use Domain\Billing\Actions\Webhooks\ProvisionTrialWorkspaceAction;
use Domain\Billing\Data\Trials\TrialCheckoutAccountData;
use Domain\Billing\Exceptions\InvalidTrialCheckoutException;
use Domain\Billing\Models\UserBilling;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Cashier;
use Stripe\Checkout\Session as StripeCheckoutSession;

/**
 * Finds the account behind a finished trial checkout for the success page
 * Stripe sends the visitor to, and decides whether the visitor may be signed in
 * to it without a password.
 *
 * The success URL carries the checkout session id, which ends up in browser
 * history and logs, so an automatic sign-in is allowed only:
 *  - for a checkout Stripe marks complete and that was created recently;
 *  - for an account the checkout itself created, that still waits for its
 *    first password ({@see ProvisionTrialWorkspaceAction}). An existing
 *    account matched by email is never signed in from a checkout: anyone can
 *    type someone else's email into Stripe Checkout;
 *  - once per checkout session.
 */
class ResolveTrialCheckoutAccountAction
{
    /** How long after the checkout opened its success link may still sign in. */
    private const int MAX_AGE_HOURS = 24;

    public function __construct(
        private readonly PasswordSetup $passwordSetup,
    ) {}

    /**
     * @throws InvalidTrialCheckoutException when the session is not a finished, recent checkout
     */
    public function execute(string $checkoutSessionId): TrialCheckoutAccountData
    {
        $session = Cashier::stripe()->checkout->sessions->retrieve($checkoutSessionId);

        if (! $this->isFinishedAndRecent($session)) {
            throw new InvalidTrialCheckoutException("Checkout session {$checkoutSessionId} is not a finished trial checkout.");
        }

        $user = UserBilling::forStripeCustomer((string) $session->customer)?->user;

        if ($user === null) {
            return new TrialCheckoutAccountData(user: null, maySignInAutomatically: false);
        }

        $maySignIn = $this->passwordSetup->isRequired($user)
            && Cache::add("trial-checkout:signed-in:{$session->id}", true, now()->addHours(self::MAX_AGE_HOURS));

        return new TrialCheckoutAccountData(user: $user, maySignInAutomatically: $maySignIn);
    }

    private function isFinishedAndRecent(StripeCheckoutSession $session): bool
    {
        return filled($session->customer)
            && $session->status === StripeCheckoutSession::STATUS_COMPLETE
            && (int) $session->created >= now()->subHours(self::MAX_AGE_HOURS)->getTimestamp();
    }
}
