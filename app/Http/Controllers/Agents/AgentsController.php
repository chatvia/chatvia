<?php

namespace App\Http\Controllers\Agents;

use App\Http\Requests\Agents\UpdateAgentRequest;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentsController
{
    public function show(string $id, Request $request)
    {
        return Inertia::render('agents/ShowAgent', [
            'agent' => $request->user()->agents()->with(['files'])->find($id),
        ]);
    }

    public function edit(string $id) {}

    public function update(UpdateAgentRequest $request, Agent $agent)
    {
        $agent->update($request->validated());
    }

    public function destroy(string $id) {}
}
