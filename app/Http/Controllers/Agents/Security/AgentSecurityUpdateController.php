<?php

namespace App\Http\Controllers\Agents\Security;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentSecurityUpdateController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'is_public' => ['required', 'boolean'],
        ]);

        $agent->update($validated);

        return back()->with('success', 'Security settings updated successfully');
    }
}
