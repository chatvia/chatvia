<?php

namespace App\Http\Controllers\Billing\Trials;

use App\Identity\Workspace\Services\PasswordSetup;
use Domain\Billing\Models\UserBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Cashier;

class TrialSuccessController
{
    public function __invoke(Request $request, PasswordSetup $passwordSetup)
    {
        $sessionId = $request->query('session_id');

        if (! $sessionId) {
            return redirect()->route('home')->with('error', 'Invalid session.');
        }

        try {
            // Retrieve the Stripe checkout session
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);

            if (! $session || ! $session->customer) {
                return redirect()->route('home')->with('error', 'Unable to retrieve checkout session.');
            }

            // Find the user by Stripe customer ID (lives on user_billing).
            $user = UserBilling::query()->where('stripe_id', $session->customer)->first()?->user;

            if (! $user) {
                return view('landing.payment-success');
            }

            // Log the user in automatically
            Auth::login($user);

            // Brand-new accounts were provisioned with a random password the
            // owner has never seen — have them choose a real one first.
            if ($passwordSetup->isRequired($user)) {
                return redirect()->route('password.setup');
            }

            // Redirect to billing success page (same as upgrading)
            return redirect()->route('billing.success');

        } catch (\Exception $e) {
            report($e);

            return redirect()->route('home')->with('error', 'Unable to complete login. Please check your email for login instructions.');
        }
    }
}
