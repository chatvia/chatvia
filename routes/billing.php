<?php

use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\BillingPaymentMethodAddController;
use App\Http\Controllers\Billing\BillingUpdateInformationController;
use App\Http\Controllers\Billing\Checkout\CheckoutExtraMessagesController;
use App\Http\Controllers\Billing\Payments\BillingPaymentMethodDefaultController;
use App\Http\Controllers\Billing\Payments\BillingPaymentMethodDeleteController;
use App\Http\Controllers\Billing\Payments\BillingPaymentMethodsController;
use App\Http\Controllers\Billing\Stripe\BillingStripePortalController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscribeController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscribeFailureController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionCancelController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionChangeController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionResumeController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionScheduleCancelController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\PlansController;
use Illuminate\Support\Facades\Route;

Route::get('subscribe', BillingSubscribeController::class)
    ->middleware(['auth', 'verified'])
    ->name('subscribe');

Route::get('subscribe/failure', BillingSubscribeFailureController::class)
    ->name('subscribe.failure');

Route::middleware(['auth', 'verified'])->prefix('billing')->name('billing.')->group(function () {

    Route::get('addons/extra-message-credits', CheckoutExtraMessagesController::class)
        ->name('addons.extra-message-credits');

    // Main billing dashboard
    Route::get('/', BillingController::class)->name('index');

    // Package selection
    Route::get('/plans', PlansController::class)->name('packages');

    // Subscription management
    Route::post('/subscriptions/change', BillingSubscriptionChangeController::class)->name('subscription.change');
    Route::post('/subscription/schedule/cancel', BillingSubscriptionScheduleCancelController::class)->name('schedule.cancel');
    Route::post('/subscription/cancel', BillingSubscriptionCancelController::class)->name('cancel');
    Route::post('/subscription/resume', BillingSubscriptionResumeController::class)->name('resume');

    Route::get('/portal', BillingStripePortalController::class)->name('portal');

    Route::get('/invoices', InvoicesController::class)->name('invoices');

    // Payment method management
    Route::get('/payments', BillingPaymentMethodsController::class)->name('payment-methods');
    Route::get('/payments/create', BillingPaymentMethodAddController::class)->name('payment-methods.create');

    // Payment method management
    Route::delete('/payments', BillingPaymentMethodDeleteController::class)->name('payment-method.remove');
    Route::post('/payments/default', BillingPaymentMethodDefaultController::class)->name('payment-method.default');

    // Billing information management
    Route::patch('/info', BillingUpdateInformationController::class)->name('information.update');
});
