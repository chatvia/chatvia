<?php

namespace Domain\Billing\Actions\Webhooks;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Domain\Billing\Actions\Subscriptions\GrantSubscriptionAction;
use Domain\Billing\Data\Stripe\CompletedCheckoutData;
use Domain\Billing\Data\Subscriptions\PlanSelectionData;
use Domain\Billing\Events\TrialWorkspaceProvisioned;
use Domain\Billing\Exceptions\TrialCheckoutEmailInUseException;
use Domain\Billing\Models\UserBilling;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Stripe\Customer;
use Support\Enums\User\UserType;

/**
 * Turns a completed trial checkout into a working workspace: finds or creates
 * the account behind the Stripe customer, syncs its Cashier subscription, and
 * grants the plan's quotas and credits.
 *
 * The account is, in order: the one already linked to the Stripe customer (a
 * returning customer), an existing account with the customer's email that has
 * no Stripe customer yet (linked now), or a new workspace owner with a random
 * password nobody knows. A new owner is flagged for first-time password setup,
 * and {@see TrialWorkspaceProvisioned} sends the welcome emails.
 *
 * Stripe Checkout lets the payer type any email address. When that address
 * belongs to an account already linked to a different Stripe customer, the
 * checkout proves nothing about who owns the account: the account is left
 * untouched (no relink, no plan change), the case is reported, and null is
 * returned so the webhook still succeeds.
 *
 * A Stripe or database failure is not swallowed: it propagates so the webhook
 * answers with an error and Stripe retries. A retry finds the account through
 * the linked Stripe customer, so it is never created twice.
 */
class ProvisionTrialWorkspaceAction
{
    public function __construct(
        private readonly SyncStripeSubscriptionAction $syncSubscription,
        private readonly GrantSubscriptionAction $grantSubscription,
        private readonly TeamService $teams,
        private readonly PasswordSetup $passwordSetup,
    ) {}

    public function execute(CompletedCheckoutData $checkout): ?User
    {
        $user = $this->accountFor($checkout);

        if ($user === null) {
            return null;
        }

        // Without the local subscription row the account is not subscribed,
        // whatever order Stripe delivers its events in.
        $billing = $user->billing()->first();

        if ($billing !== null && $checkout->subscriptionId !== null) {
            $this->syncSubscription->execute($billing, $checkout->subscriptionId);
        }

        $this->grantSubscription->execute($user, new PlanSelectionData(
            package: $checkout->metadata->package(),
            isYearly: $checkout->metadata->isYearly(),
        ));

        return $user;
    }

    private function accountFor(CompletedCheckoutData $checkout): ?User
    {
        $linked = UserBilling::forStripeCustomer($checkout->customerId)?->user;

        if ($linked !== null) {
            return $linked;
        }

        $customer = Cashier::stripe()->customers->retrieve($checkout->customerId);

        $existing = User::query()->where('email', (string) $customer->email)->first();

        if ($existing !== null) {
            return $this->linkExistingAccount($existing, $checkout);
        }

        $user = $this->createAccount($customer, $checkout);

        // The random password is never shown to anyone; RequirePasswordSetup
        // walks the owner through choosing one as soon as they arrive.
        $this->passwordSetup->flag($user);

        TrialWorkspaceProvisioned::dispatch($user, $checkout->metadata->package());

        return $user;
    }

    /**
     * Links an existing account that has never paid. An account that already
     * belongs to another Stripe customer is not touched: anyone can type its
     * email address into Stripe Checkout.
     */
    private function linkExistingAccount(User $existing, CompletedCheckoutData $checkout): ?User
    {
        $currentCustomerId = $existing->billing()->value('stripe_id');

        if (filled($currentCustomerId) && $currentCustomerId !== $checkout->customerId) {
            report(TrialCheckoutEmailInUseException::forCheckout($checkout->sessionId, $checkout->customerId));

            return null;
        }

        $existing->billing()->updateOrCreate([], ['stripe_id' => $checkout->customerId]);

        return $existing;
    }

    private function createAccount(Customer $customer, CompletedCheckoutData $checkout): User
    {
        $email = (string) $customer->email;
        $countryCode = isset($customer->address->country) ? strtoupper($customer->address->country) : null;

        return DB::transaction(function () use ($customer, $email, $countryCode, $checkout): User {
            // `type` must be set explicitly: the UserObserver provisions the
            // billing row only for workspace and admin accounts, and it reads
            // the type at `created` time, before the column default is known.
            /** @var User $user */
            $user = User::query()->create([
                'name' => $customer->name ?? explode('@', $email)[0],
                'email' => $email,
                'type' => UserType::Workspace,
                'password' => Hash::make(Str::random()),
                'domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
                'registered_ip' => $checkout->metadata->userIp(),
                'registered_country_code' => $countryCode,
                'email_verified_at' => now(),
            ]);

            $user->billing()->updateOrCreate([], ['stripe_id' => $checkout->customerId]);

            $this->teams->createDefaultTeam($user);

            return $user;
        });
    }
}
