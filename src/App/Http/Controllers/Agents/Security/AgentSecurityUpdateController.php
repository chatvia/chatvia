<?php

namespace App\Http\Controllers\Agents\Security;

use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentSecurityUpdateController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        $validated = $request->validate([
            'is_public' => ['required', 'boolean'],
        ]);

        $agent->update($validated);

        return back()->with('success', 'Security settings updated successfully');
    }
}
