<?php

use App\Http\Controllers\Billing\AddPaymentMethodController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\CancelSubscriptionController;
use App\Http\Controllers\Billing\ChangeSubscriptionController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\CheckoutFailureController;
use App\Http\Controllers\Billing\PaymentMethodsController;
use App\Http\Controllers\Billing\ResumeSubscriptionController;
use App\Http\Controllers\Billing\StripePortalController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\PackagesController;
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
    Route::get('/packages', PackagesController::class)->name('packages');

    Route::post('/cancel', CancelSubscriptionController::class)->name('cancel');

    Route::post('/resume', ResumeSubscriptionController::class)->name('resume');

    Route::get('/portal', StripePortalController::class)->name('portal');

    Route::get('/invoices', InvoicesController::class)->name('invoices');

    Route::get('/payment-methods', PaymentMethodsController::class)->name('payment-methods');

    Route::get('/payment-methods/create', AddPaymentMethodController::class)->name('payment-methods.create');

    Route::post('/subscription/change', ChangeSubscriptionController::class)->name('subscription.change');

    // Payment method management
    Route::delete('/payment-method/remove', [BillingController::class, 'removePaymentMethod'])->name('payment-method.remove');
    Route::post('/payment-method/default', [BillingController::class, 'setDefaultPaymentMethod'])->name('payment-method.default');

    // Billing information management
    Route::patch('/information/update', [BillingController::class, 'updateBillingInformation'])->name('information.update');

    // Subscription management
    //    Route::post('/subscribe', [BillingController::class, 'subscribe'])->name('subscribe');
    //    Route::get('/success', [BillingController::class, 'success'])->name('success');
    //    Route::post('/cancel', [BillingController::class, 'cancelSubscription'])->name('cancel');
    //    Route::post('/resume', [BillingController::class, 'resumeSubscription'])->name('resume');
    //
    //    // Trial management
    //    Route::post('/trial/start', [BillingController::class, 'startTrial'])->name('trial.start');
    //
    //    // Payment methods
    //    Route::get('/payment-methods', [BillingController::class, 'paymentMethods'])->name('payment-methods');
    //    Route::post('/payment-method/update', [BillingController::class, 'updatePaymentMethod'])->name('payment-method.update');
    //    Route::delete('/payment-method/remove', [BillingController::class, 'removePaymentMethod'])->name('payment-method.remove');
    //    Route::post('/payment-method/default', [BillingController::class, 'setDefaultPaymentMethod'])->name('payment-method.default');
    //
    //    // Invoices
    //    Route::get('/invoices', [BillingController::class, 'invoices'])->name('invoices');
    //    Route::get('/invoice/{invoiceId}/download', [BillingController::class, 'downloadInvoice'])->name('invoice.download');
});
