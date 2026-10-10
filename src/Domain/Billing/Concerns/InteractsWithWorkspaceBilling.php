<?php

namespace Domain\Billing\Concerns;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Contracts\Entitlements;
use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Enums\Capability;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Models\UserBilling;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Models\UserQuota;
use Domain\Billing\Services\ChatterMessageAllowance;
use Domain\Billing\Services\MessageCredits;
use Domain\Billing\Services\PlanCatalog;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Billing\Services\WorkspaceConsumption;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Plan, subscription, capabilities, message credits, add-ons and the quota row
 * for the {@see User} account. Thin accessors: the rules live in the Billing
 * services and the {@see Entitlements} port, which answers "everything
 * allowed" when billing is off.
 *
 * Billing is keyed to the workspace OWNER: everything here resolves through
 * `workspace()`, so a teammate or chatter reads the owner's plan and credit
 * pool. The workspace-scoped relations (`credits`, `credit`, `addons`,
 * `quota`) are lazy-only; only `billing` and `chatterMessageCredit` bind to
 * the account's own rows.
 */
trait InteractsWithWorkspaceBilling
{
    /**
     * Stripe-side billing state. Only workspace owners (and admins) ever have a
     * row; chatters and employees do not.
     *
     * @return HasOne<UserBilling, $this>
     */
    public function billing(): HasOne
    {
        return $this->hasOne(UserBilling::class);
    }

    /**
     * Cashier customer for this user's account — the workspace owner's
     * `UserBilling` row, or null if the workspace has none yet. Use this
     * anywhere you'd otherwise reach for `$user->subscribed()` etc.
     */
    public function accountBilling(): ?UserBilling
    {
        return $this->remember('billing', fn (): ?UserBilling => $this->workspace()->billing);
    }

    /**
     * Same as `accountBilling()` but asserts the row exists. Use from code
     * that calls Cashier methods (createSetupIntent, redirectToBillingPortal,
     * findPaymentMethod, updateStripeCustomer, ...) — those live on the
     * Billable `UserBilling`, not on `User`. The trial-checkout Stripe webhook
     * provisions a billing row for every workspace owner, so the null path is
     * a real bug (workspace mis-wiring, missing provisioning in tests) and
     * should crash loudly rather than return a misleading null.
     */
    public function accountBillingOrFail(): UserBilling
    {
        return $this->accountBilling() ?? throw new \RuntimeException(
            'No UserBilling row for user '.$this->id.'. The trial-checkout webhook provisions one for every workspace owner; tests must create it explicitly.'
        );
    }

    /**
     * The plan the account's subscription is on (the default plan without one),
     * as {@see PlanCatalog} shows it.
     *
     * @return array<string, mixed>
     */
    public function getCurrentPackage(): array
    {
        return $this->remember(
            'currentPackage',
            fn (): array => app(PlanCatalog::class)->currentPackage($this->accountBilling()),
        );
    }

    /**
     * The id of the plan the account is on (a `billing_plans.key`).
     *
     * @return Attribute<mixed, never>
     */
    public function plan(): Attribute
    {
        return Attribute::get(fn (): mixed => $this->getCurrentPackage()['id']);
    }

    /**
     * Whether the account has a paid subscription or a running trial. Always
     * true when billing is turned off (see {@see Entitlements}).
     */
    public function hasActiveSubscription(): bool
    {
        return $this->remember(
            'hasActiveSubscription',
            fn (): bool => app(Entitlements::class)->hasActiveBilling($this),
        );
    }

    /**
     * @return Attribute<bool, never>
     */
    public function isPaid(): Attribute
    {
        // Every account is on a trial or a subscription.
        return Attribute::get(fn (): bool => $this->hasActiveSubscription());
    }

    /**
     * The plan features this account has. Resolved by {@see Entitlements}: the
     * workspace's plan when billing is on, every feature when it is off.
     *
     * @return Attribute<BillingPackageCapabilitiesData, never>
     */
    public function capabilities(): Attribute
    {
        return Attribute::get(
            fn (): BillingPackageCapabilitiesData => app(Entitlements::class)->capabilities($this),
        );
    }

    public function hasCapability(Capability $capability): bool
    {
        // `capabilities()` returns the Eloquent Attribute container; calling
        // it as a method on the instance bypasses the accessor and yields
        // the wrapper, not the resolved DTO. Access via the magic property
        // (`$this->capabilities`) so Eloquent runs the get-closure and we
        // get a `BillingPackageCapabilitiesData` with the named capability.
        return $this->capabilities->{$capability->value} === true;
    }

    /**
     * Whether this account may hide the "Powered by Chatvia" badge — the
     * workspace-wide entitlement that hides it on every agent's widget and
     * hosted chat. Granted two ways: the plan bundles white-label (Pro and
     * Enterprise carry the `removeBranding` capability), OR the account owns
     * the standalone "remove branding" add-on (the path lower tiers use).
     * Resolved through the account owner's plan + Cashier billing, so
     * employees/chatters inherit the workspace answer.
     */
    public function canHideBranding(): bool
    {
        return $this->remember(
            'canHideBranding',
            fn (): bool => $this->capabilities->removeBranding
                || (bool) $this->accountBilling()?->subscribed(Addon::RemoveBranding->value),
        );
    }

    /**
     * The workspace's message-credit pool: the send decision and the cached
     * counters behind it.
     */
    public function creditSystem(): UserCreditSystem
    {
        return app(UserCreditSystem::class)->forWorkspace($this->workspace()->id);
    }

    public function consumableMessages(): int
    {
        return $this->creditSystem()->consumableMessages();
    }

    /**
     * Message-credit figures for screens ("X messages left this cycle"). For
     * the send decision use {@see creditSystem()}.
     */
    public function messageCredits(): MessageCredits
    {
        return new MessageCredits($this);
    }

    /**
     * Knowledge consumption (pages, minutes, links) and the upload-quota check.
     */
    public function consumption(): WorkspaceConsumption
    {
        return new WorkspaceConsumption($this);
    }

    /**
     * Account-scoped via workspace() — **lazy-only**. Eager-loading
     * (`with`/`load`) returns the wrong rows for an employee/chatter; eager-load
     * through the workspace leg instead: `loadMissing(['parentWorkspace.credits', 'credits'])`.
     *
     * @return HasMany<UserCredit, self>
     */
    public function credits(): HasMany
    {
        $workspace = $this->workspace();

        return $workspace->hasMany(UserCredit::class);
    }

    /**
     * Account-scoped via workspace() — **lazy-only**. Eager-loading
     * (`with`/`load`) returns the wrong rows for an employee/chatter; eager-load
     * through the workspace leg instead: `loadMissing(['parentWorkspace.credit', 'credit'])`.
     *
     * @return HasOne<UserCredit, self>
     */
    public function credit(): HasOne
    {
        $workspace = $this->workspace();

        return $workspace->hasOne(UserCredit::class)
            ->where('ends_at', '>=', now())
            ->latestOfMany('ends_at');
    }

    /**
     * This chatter's OWN per-chatter monthly message-limit credit (latest
     * period). Unlike {@see credit()} / {@see credits()} / {@see creditSystem()},
     * which resolve the workspace OWNER's billing pool via `workspace()`,
     * this binds literally to the chatter's own `user_credits` rows — the home
     * of the per-chatter cap managed by {@see ChatterMessageAllowance}.
     * Eager-load `chatterMessageCredit.usage` to render usage without N+1.
     *
     * @return HasOne<UserCredit, $this>
     */
    public function chatterMessageCredit(): HasOne
    {
        return $this->hasOne(UserCredit::class, 'user_id')->latestOfMany('ends_at');
    }

    /**
     * Account-scoped via workspace() — **lazy-only**. Eager-loading
     * (`with`/`load`) returns the wrong rows for an employee/chatter; eager-load
     * through the workspace leg instead: `loadMissing(['parentWorkspace.addons', 'addons'])`.
     *
     * @return HasMany<UserAddon, self>
     */
    public function addons(): HasMany
    {
        $workspace = $this->workspace();

        return $workspace->hasMany(UserAddon::class);
    }

    /**
     * Account-scoped via workspace() — **lazy-only**. Eager-loading
     * (`with('quota')` / `load('quota')`) builds the relation on a bare instance
     * and returns null for an employee/chatter; eager-load through the workspace
     * leg instead: `loadMissing(['parentWorkspace.quota', 'quota'])` (see AgentTeamController).
     *
     * @return HasOne<UserQuota, self>
     */
    public function quota(): HasOne
    {
        $workspace = $this->workspace();

        return $workspace->hasOne(UserQuota::class);
    }
}
