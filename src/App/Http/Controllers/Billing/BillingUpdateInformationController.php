<?php

namespace App\Http\Controllers\Billing;

use Exception;
use Illuminate\Http\Request;

class BillingUpdateInformationController
{
    public function __invoke(Request $request)
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

        } catch (Exception $exception) {
            return back()->withErrors(['billing' => 'Failed to update billing information']);
        }
    }
}
