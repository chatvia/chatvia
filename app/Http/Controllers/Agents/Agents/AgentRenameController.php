<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentRenameController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('agents', 'name')
                    ->ignore($agent->id)
                    ->where('user_id', $request->user()->id),
            ],
        ]);

        $agent->update([
            'name' => $request->name,
        ]);

        return redirect()->back()->with('success', 'Agent renamed successfully');
    }
}
