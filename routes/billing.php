<?php

use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\BillingPaymentMethodAddController;
use App\Http\Controllers\Billing\BillingUpdateInformationController;
use App\Http\Controllers\Billing\Checkout\CheckoutController;
use App\Http\Controllers\Billing\Checkout\CheckoutFailureController;
use App\Http\Controllers\Billing\Payments\BillingPaymentMethodDefaultController;
use App\Http\Controllers\Billing\Payments\BillingPaymentMethodDeleteController;
use App\Http\Controllers\Billing\Payments\BillingPaymentMethodsController;
use App\Http\Controllers\Billing\Stripe\BillingStripePortalController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionCancelController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionChangeController;
use App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionResumeController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\PlansController;
use Illuminate\Support\Facades\Route;

Route::get('checkout', CheckoutController::class)
    ->middleware(['auth', 'verified'])
    ->name('checkout');

Route::get('checkout/failure', CheckoutFailureController::class)
    ->name('checkout.failure');

Route::middleware(['auth', 'verified'])->prefix('billing')->name('billing.')->group(function () {
    // Main billing dashboard
    Route::get('/', BillingController::class)->name('index');

    // Package selection
    Route::get('/plans', PlansController::class)->name('packages');

    // Subscription management
    Route::post('/subscriptions/change', BillingSubscriptionChangeController::class)->name('subscription.change');
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
