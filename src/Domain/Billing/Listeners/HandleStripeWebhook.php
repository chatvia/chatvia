<?php

namespace Domain\Billing\Listeners;

use Domain\Billing\Actions\Webhooks\GrantOneOffAddonAction;
use Domain\Billing\Actions\Webhooks\GrantRenewalAction;
use Domain\Billing\Actions\Webhooks\ProvisionTrialWorkspaceAction;
use Domain\Billing\Data\Stripe\CompletedCheckoutData;
use Domain\Billing\Data\Stripe\PaidInvoiceData;
use Domain\Billing\Data\Stripe\StripeEventData;
use Domain\Billing\Enums\PurchaseType;
use Domain\Billing\Enums\RegistrationType;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;
use RuntimeException;
use Throwable;

/**
 * Entry point for every Stripe webhook Cashier receives. It only guards and
 * routes; the work is done by the Actions in `Actions/Webhooks`:
 *
 *  - a completed trial checkout → {@see ProvisionTrialWorkspaceAction}
 *  - a completed credit-pack checkout → {@see GrantOneOffAddonAction}
 *  - a paid subscription invoice → {@see GrantRenewalAction}
 *
 * Add-on subscription quotas are synced after Cashier has stored the change
 * (see {@see SyncAddonQuotasFromStripe}).
 *
 * Guards, in order: nothing is granted while billing is turned off; an
 * unsigned webhook is refused outside local and testing; and each Stripe event
 * id is processed once. When an Action fails, the event id is released and the
 * exception is rethrown, so the webhook answers with an error and Stripe
 * retries it.
 */
class HandleStripeWebhook
{
    /** How long a processed event id is remembered — Stripe retries for up to three days. */
    private const int EVENT_CLAIM_DAYS = 7;

    public function __construct(
        private readonly Container $container,
    ) {}

    public function handle(WebhookReceived $event): void
    {
        // The webhook route is not registered while billing is off; this
        // covers any other way the event could be dispatched.
        if (! config('billing.enabled', true)) {
            return;
        }

        $this->assertWebhookIsSigned();

        $stripeEvent = StripeEventData::fromPayload($event->payload);

        if (! $this->claim($stripeEvent)) {
            Log::info('Stripe webhook replay skipped', ['event_id' => $stripeEvent->id]);

            return;
        }

        try {
            $this->route($stripeEvent);
        } catch (Throwable $exception) {
            // Otherwise a transient failure would mark the event as handled
            // and Stripe's retry would be skipped for good.
            $this->release($stripeEvent);

            throw $exception;
        }
    }

    /**
     * Cashier checks the Stripe signature only when a webhook secret is set, so
     * without one every POST to the webhook route is trusted and a forged event
     * could grant plans and credits. This refuses them in every shared
     * environment, production included (the secret is set in the admin panel,
     * Settings → Billing). Cashier dispatches WebhookReceived before its own
     * handlers run, so throwing here also stops Cashier from syncing
     * subscriptions from the unsigned payload. Local development and the test
     * suite may run unsigned.
     */
    private function assertWebhookIsSigned(): void
    {
        if (filled(config('cashier.webhook.secret')) || app()->environment('local', 'testing')) {
            return;
        }

        throw new RuntimeException(
            'The Stripe webhook signing secret is not set (admin panel: Settings → Billing). Refusing to process an unsigned Stripe webhook.'
        );
    }

    /**
     * The Actions are resolved per event (not injected) so each delivery gets
     * fresh instances from the container.
     */
    private function route(StripeEventData $event): void
    {
        if ($event->is(StripeEventData::CHECKOUT_COMPLETED)) {
            $this->routeCompletedCheckout(CompletedCheckoutData::fromEvent($event));

            return;
        }

        if ($event->is(StripeEventData::INVOICE_PAID)) {
            $invoice = PaidInvoiceData::fromInvoice($event->object);

            if ($invoice->isForSubscription()) {
                $this->container->make(GrantRenewalAction::class)->execute($invoice);
            }
        }
    }

    private function routeCompletedCheckout(CompletedCheckoutData $checkout): void
    {
        if ($checkout->metadata->registrationType() === RegistrationType::TrialRegistration) {
            $this->container->make(ProvisionTrialWorkspaceAction::class)->execute($checkout);

            return;
        }

        if ($checkout->metadata->purchaseType() === PurchaseType::Addon) {
            $this->container->make(GrantOneOffAddonAction::class)->execute($checkout);
        }
    }

    /**
     * Stripe retries webhooks on transient failures, so the same event can
     * arrive more than once. Cache::add is atomic: false means the event was
     * already processed. Events without an id are always processed.
     */
    private function claim(StripeEventData $event): bool
    {
        return $event->id === null
            || Cache::add($this->claimKey($event->id), true, now()->addDays(self::EVENT_CLAIM_DAYS));
    }

    private function release(StripeEventData $event): void
    {
        if ($event->id !== null) {
            Cache::forget($this->claimKey($event->id));
        }
    }

    private function claimKey(string $eventId): string
    {
        return "stripe:event:{$eventId}";
    }
}
