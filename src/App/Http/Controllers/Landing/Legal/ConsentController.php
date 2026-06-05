<?php

namespace App\Http\Controllers\Landing\Legal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsentController extends Controller
{
    /**
     * Display the consent page.
     */
    public function show(): Response
    {
        return Inertia::render('Consent');
    }

    /**
     * Handle the consent form submission.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'data_region' => 'required|in:us,eu',
            'marketing_consent' => 'boolean',
            'analytics_consent' => 'boolean',
        ]);

        if (auth()->check()) {
            $user = auth()->user();

            $user->update([
                'data_region' => $validated['data_region'],
                'marketing_consent' => $validated['marketing_consent'] ?? false,
                'analytics_consent' => $validated['analytics_consent'] ?? false,
                'consent_given_at' => now(),
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Your consent preferences have been saved successfully.');
    }
}
