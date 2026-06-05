<?php

namespace App\Http\Controllers\Agent\Security;

use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;

class AgentSecurityUpdateController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $request->validate(['is_public' => ['required', 'boolean']]);

        $agent->update(['is_public' => $request->boolean('is_public')]);

        return back()->with('success', 'Security settings updated successfully');
    }
}
