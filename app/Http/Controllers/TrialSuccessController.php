<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Cashier;

class TrialSuccessController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
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

            // Find the user by Stripe customer ID
            $user = User::query()->where('stripe_id', $session->customer)->first();

            if (! $user) {
                // User might not be created yet (webhook processing delay)
                // Redirect with a message to check email
                return redirect()->route('home')->with('success', 'Payment successful! Please check your email for login instructions.');
            }

            // Log the user in automatically
            Auth::login($user);

            // Redirect to dashboard with success message
            return redirect()->route('agents.index')->with('success', 'Welcome! Your trial has started successfully.');

        } catch (\Exception $e) {
            \Log::error('Trial success error', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
            ]);

            return redirect()->route('home')->with('error', 'Unable to complete login. Please check your email for login instructions.');
        }
    }
}
