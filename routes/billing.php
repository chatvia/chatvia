<?php

use App\Http\Controllers\Billing\Addons\UpdateAutoRechargeController;
use App\Http\Controllers\Billing\Checkout\CheckoutCustomDomainController;
use App\Http\Controllers\Billing\Checkout\CheckoutExtraAgentsController;
use App\Http\Controllers\Billing\Checkout\CheckoutExtraMessagesController;
use App\Http\Controllers\Billing\Checkout\CheckoutMembersController;
use App\Http\Controllers\Billing\Checkout\CheckoutRemoveBrandingController;
use App\Http\Controllers\Billing\Home\BillingController;
use App\Http\Controllers\Billing\Home\BillingDataController;
use App\Http\Controllers\Billing\Home\BillingUpdateInformationController;
use App\Http\Controllers\Billing\Invoices\InvoiceController;
use App\Http\Controllers\Billing\Payments\BillingPaymentMethodController;
use App\Http\Controllers\Billing\Payments\BillingPaymentMethodDefaultController;
use App\Http\Controllers\Billing\Payments\BillingPaymentMethodDeleteController;
use App\Http\Controllers\Billing\Payments\BillingPaymentSuccessController;
use App\Http\Controllers\Billing\Plans\PlanController;
use App\Http\Controllers\Billing\Stripe\BillingStripePortalController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscribeController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscribeFailureController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionCancelController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionChangeController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionResumeController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionScheduleCancelController;
use App\Http\Controllers\Billing\Subscriptions\CancelCustomDomainController;
use App\Http\Controllers\Billing\Subscriptions\CancelExtraAgentsController;
use App\Http\Controllers\Billing\Subscriptions\CancelMembersController;
use App\Http\Controllers\Billing\Subscriptions\CancelRemoveBrandingController;
use App\Http\Middleware\Auth\DenyIfEmployee;
use Illuminate\Support\Facades\Route;

Route::prefix('my')->group(function (): void {
    Route::get('subscribe', BillingSubscribeController::class)
        ->middleware(['auth', 'verified', DenyIfEmployee::class])
        ->name('subscribe');

    Route::get('subscribe/failure', BillingSubscribeFailureController::class)
        ->name('subscribe.failure');

    Route::middleware(['auth', 'verified', DenyIfEmployee::class, 'can.access.dashboard'])->prefix('billing')->name('billing.')->group(function (): void {

        Route::get('addons/extra-message-credits', CheckoutExtraMessagesController::class)
            ->name('addons.extra-message-credits');

        Route::get('addons/extra-agents', CheckoutExtraAgentsController::class)
            ->name('addons.extra-agents');

        Route::get('addons/custom-domains', CheckoutCustomDomainController::class)
            ->name('addons.custom-domains');

        Route::get('addons/remove-branding', CheckoutRemoveBrandingController::class)
            ->name('addons.remove-branding');

        Route::get('addons/members', CheckoutMembersController::class)
            ->name('addons.members');

        Route::post('addons/auto-recharge', UpdateAutoRechargeController::class)
            ->name('addons.auto-recharge.update');

        Route::post('addons/extra-agents/cancel', CancelExtraAgentsController::class)
            ->name('addons.extra-agents.cancel');

        Route::post('addons/custom-domains/cancel', CancelCustomDomainController::class)
            ->name('addons.custom-domains.cancel');

        Route::post('addons/remove-branding/cancel', CancelRemoveBrandingController::class)
            ->name('addons.remove-branding.cancel');

        Route::post('addons/members/cancel', CancelMembersController::class)
            ->name('addons.members.cancel');

        // Main billing dashboard
        Route::get('/', BillingController::class)->name('index');

        // Payment success page (shown after Stripe checkout)
        Route::get('/success', BillingPaymentSuccessController::class)->name('success');

        // Billing data API endpoint
        Route::get('/data', BillingDataController::class)->name('data');

        // Package selection
        Route::get('/plans', PlanController::class)->name('packages');

        // Subscription management
        Route::post('/subscriptions/change', BillingSubscriptionChangeController::class)->name('subscription.change');
        Route::post('/subscription/schedule/cancel', BillingSubscriptionScheduleCancelController::class)->name('schedule.cancel');
        Route::post('/subscription/cancel', BillingSubscriptionCancelController::class)->name('cancel');
        Route::post('/subscription/resume', BillingSubscriptionResumeController::class)->name('resume');

        Route::get('/portal', BillingStripePortalController::class)->name('portal');

        Route::get('/invoices', InvoiceController::class)->name('invoices');

        // Payment method management
        Route::get('/payments', BillingPaymentMethodController::class)->name('payment-methods');

        // Payment method management
        Route::delete('/payments', BillingPaymentMethodDeleteController::class)->name('payment-method.remove');
        Route::post('/payments/default', BillingPaymentMethodDefaultController::class)->name('payment-method.default');

        // Billing information management
        Route::patch('/info', BillingUpdateInformationController::class)->name('information.update');
    });
});
