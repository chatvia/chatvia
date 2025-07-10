<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $currentPackage = $user->getCurrentPackage();

        return Inertia::render('billing/Index', [
            'current_package' => $currentPackage,
            'on_trial' => $user->onTrial(),
            'subscription_canceled' => $user->subscription()?->canceled(),
            'trial_ends_at' => $user->trialEndsAt()?->toFormattedDateString(),
            'subscription' => $user->subscription(),
            'subscribed' => $user->subscribed(),
        ]);
    }

    /**
     * Show the package selection page
     */
    public function packages() {}

    /**
     * Subscribe to a package
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'package' => 'required|string|in:free,starter,professional,enterprise',
            'billing_cycle' => 'required|string|in:monthly,yearly',
        ]);

        $user = auth()->user();

        \Log::info('Subscription request received', [
            'user_id' => $user->id,
            'package' => $request->package,
            'billing_cycle' => $request->billing_cycle,
        ]);

        try {
            $result = $this->billingService->subscribe(
                $user,
                $request->package,
                $request->billing_cycle
            );

            \Log::info('Subscription service result', $result);

            if ($result['success']) {
                // If there's a checkout URL, redirect to Stripe
                if (isset($result['checkout_url'])) {
                    \Log::info('Redirecting to Stripe Checkout', ['url' => $result['checkout_url']]);

                    return redirect()->away($result['checkout_url']);
                }

                // For free plan or other success cases
                return redirect()->route('billing.index')->with('success', $result['message']);
            }

            // Handle error case
            \Log::warning('Subscription failed', ['error' => $result['message']]);

            return back()->withErrors(['subscription' => $result['message']]);

        } catch (\Exception $exception) {
            \Log::error('Subscription exception', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()->withErrors(['subscription' => 'Subscription failed: '.$exception->getMessage()]);
        }
    }

    /**
     * Handle successful payment from Stripe
     */
    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');

        if (! $sessionId) {
            return redirect()->route('billing.packages')->withErrors(['payment' => 'Invalid payment session']);
        }

        try {
            // Retrieve the session from Stripe to get the subscription details
            $stripe = new \Stripe\StripeClient(config('cashier.secret'));
            $session = $stripe->checkout->sessions->retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                $user = auth()->user();

                // Update user's package based on metadata
                if (isset($session->metadata->package_id)) {
                    $user->updatePackage($session->metadata->package_id);
                }

                return redirect()->route('billing.index')->with('success', 'Subscription activated successfully!');
            }

            return redirect()->route('billing.packages')->withErrors(['payment' => 'Payment was not completed']);

        } catch (\Exception $exception) {
            return redirect()->route('billing.packages')->withErrors(['payment' => 'Failed to verify payment: '.$exception->getMessage()]);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Request $request)
    {
        $request->validate([
            'immediately' => 'boolean',
        ]);

        $user = auth()->user();
        $result = $this->billingService->cancelSubscription($user, $request->boolean('immediately'));

        if ($result['success']) {
            return redirect()->route('billing.index')->with('success', $result['message']);
        }

        return back()->withErrors(['cancellation' => $result['message']]);
    }

    /**
     * Resume subscription
     */
    public function resumeSubscription()
    {
        $user = auth()->user();
        $result = $this->billingService->resumeSubscription($user);

        if ($result['success']) {
            return redirect()->route('billing.index')->with('success', $result['message']);
        }

        return back()->withErrors(['resume' => $result['message']]);
    }

    /**
     * Update payment method
     */
    public function updatePaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $user = auth()->user();
        $result = $this->billingService->updatePaymentMethod($user, $request->payment_method_id);

        if ($result['success']) {
            return redirect()->route('billing.index')->with('success', $result['message']);
        }

        return back()->withErrors(['payment_method' => $result['message']]);
    }

    /**
     * Start trial
     */
    public function startTrial(Request $request)
    {
        $request->validate([
            'package' => 'nullable|string|in:starter,professional,enterprise',
        ]);

        $user = auth()->user();
        $result = $this->billingService->startTrial($user, $request->package);

        if ($result['success']) {
            return redirect()->route('billing.index')->with('success', $result['message']);
        }

        return back()->withErrors(['trial' => $result['message']]);
    }

    /**
     * Download invoice
     */
    public function downloadInvoice(Request $request, string $invoiceId)
    {
        $user = auth()->user();

        try {
            return $user->downloadInvoice($invoiceId, [
                'vendor' => config('app.name'),
                'product' => 'Subscription',
            ]);
        } catch (\Exception $exception) {
            return back()->withErrors(['invoice' => 'Failed to download invoice']);
        }
    }

    /**
     * Get user's invoices
     */
    public function invoices()
    {
        $user = auth()->user();
        $invoices = $user->invoices();

        return Inertia::render('billing/Invoices', [
            'invoices' => collect($invoices)->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'date' => $invoice->date()->toFormattedDateString(),
                    'total' => $invoice->total(),
                    'download_url' => route('billing.invoice.download', $invoice->id),
                ];
            }),
        ]);
    }

    /**
     * Setup payment method page
     */
    public function paymentMethods()
    {
        $user = auth()->user();

        return Inertia::render('billing/PaymentMethods', [
            'intent' => $user->createSetupIntent(),
            'paymentMethods' => $user->paymentMethods()->map(function ($paymentMethod) {
                return [
                    'id' => $paymentMethod->id,
                    'type' => $paymentMethod->type,
                    'card' => $paymentMethod->card ?? null,
                    'is_default' => $paymentMethod->id === auth()->user()->defaultPaymentMethod()?->id,
                ];
            }),
            'stripeKey' => config('cashier.key'),
        ]);
    }

    /**
     * Remove payment method
     */
    public function removePaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $user = auth()->user();

        try {
            $paymentMethod = $user->findPaymentMethod($request->payment_method_id);

            if ($paymentMethod) {
                $paymentMethod->delete();

                return redirect()->route('billing.payment-methods')->with('success', 'Payment method removed successfully');
            }

            return back()->withErrors(['payment_method' => 'Payment method not found']);

        } catch (\Exception $exception) {
            return back()->withErrors(['payment_method' => 'Failed to remove payment method']);
        }
    }

    /**
     * Set default payment method
     */
    public function setDefaultPaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $user = auth()->user();

        try {
            $user->updateDefaultPaymentMethod($request->payment_method_id);

            return redirect()->route('billing.payment-methods')->with('success', 'Default payment method updated');

        } catch (\Exception $exception) {
            return back()->withErrors(['payment_method' => 'Failed to update default payment method']);
        }
    }

    /**
     * Update billing information
     */
    public function updateBillingInformation(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'address.line1' => 'required|string|max:255',
            'address.line2' => 'nullable|string|max:255',
            'address.city' => 'required|string|max:255',
            'address.state' => 'required|string|max:255',
            'address.postal_code' => 'required|string|max:20',
            'address.country' => 'required|string|max:2',
        ]);

        $user = auth()->user();

        try {
            // Update user basic information
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'billing_address' => $request->address,
            ]);

            $user->updateStripeCustomer([
                'name' => $request->name,
                'email' => $request->email,
                'address' => [
                    'line1' => $request->address['line1'],
                    'line2' => $request->address['line2'] ?? null,
                    'city' => $request->address['city'],
                    'state' => $request->address['state'],
                    'postal_code' => $request->address['postal_code'],
                    'country' => $request->address['country'],
                ],
            ]);

            return redirect()->route('billing.payment-methods')->with('success', 'Billing information updated successfully');

        } catch (\Exception $exception) {
            return back()->withErrors(['billing' => 'Failed to update billing information']);
        }
    }
}
