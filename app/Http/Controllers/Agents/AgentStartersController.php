<?php

namespace App\Http\Controllers\Agents;

use App\Models\Agent;
use App\Models\Starter;
use Illuminate\Http\Request;

class AgentStartersController
{
    public function store(Request $request, Agent $agent): Starter
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $request->validate([
            'text' => ['required', 'string', 'max:255'],
        ]);

        return $agent->starters()->create(['text' => $request->input('text')]);
    }

    public function destroy(Request $request, Agent $agent, int $id): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $agent->starters()->where('id', $id)->delete();
    }
}
