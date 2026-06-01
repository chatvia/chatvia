<?php

namespace App\Http\Controllers\Agents\Security;

use Domain\Agents\Actions\Settings\UpdateAgentSecurityAction;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentSecurityUpdateController
{
    public function __invoke(Request $request, Agent $agent, UpdateAgentSecurityAction $update)
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $request->validate(['is_public' => ['required', 'boolean']]);

        $update->execute($agent, $request->boolean('is_public'));

        return back()->with('success', 'Security settings updated successfully');
    }
}
