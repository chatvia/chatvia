<?php

namespace App\Http\Controllers\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;

class UpdateModelsController extends Controller
{
    public function __invoke(Request $request, Agent $agent)
    {
        // Ensure the user owns the agent
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'selected_models' => ['array'],
            'selected_models.*' => ['string'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'top_k' => ['required', 'integer', 'min:1', 'max:100'],
            'top_p' => ['required', 'numeric', 'min:0.1', 'max:1'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:32000'],
            'presence_penalty' => ['required', 'numeric', 'min:-2', 'max:2'],
            'frequency_penalty' => ['required', 'numeric', 'min:-2', 'max:2'],
        ]);

        $user = $request->user();

        // Check if user can access the selected models
        foreach ($validated['selected_models'] as $modelId) {
            if (! $user->canAccessFeature('models', $modelId)) {
                return back()->withErrors([
                    'selected_models' => "The model '{$modelId}' is not available in your current plan. Please upgrade to access premium models.",
                ]);
            }
        }

        // Update agent settings
        $agent->bulkSetSettings([
            'selected_models' => $validated['selected_models'],
            'temperature' => $validated['temperature'],
            'top_k' => $validated['top_k'],
            'top_p' => $validated['top_p'],
            'max_tokens' => $validated['max_tokens'],
            'presence_penalty' => $validated['presence_penalty'],
            'frequency_penalty' => $validated['frequency_penalty'],
        ]);

        return back()->with('success', 'Model configuration updated successfully');
    }
}
