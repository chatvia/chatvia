<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Actions\CreateAgentStarterAction;
use Domain\Agents\Actions\DeleteAgentStarterAction;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Starter;
use Illuminate\Http\Request;

class AgentStartersController
{
    public function store(Request $request, Agent $agent, CreateAgentStarterAction $create): Starter
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $request->validate(['text' => ['required', 'string', 'max:255']]);

        return $create->execute($agent, $request->string('text')->toString());
    }

    public function destroy(Request $request, Agent $agent, int $id, DeleteAgentStarterAction $delete): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $delete->execute($agent, $id);
    }
}
