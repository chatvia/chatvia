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

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('agents', 'name')
                    ->ignore($agent->id)
                    ->where('user_id', $request->user()->id),
            ],
        ];

        // Add subdomain validation for knowledge_base agents
        if ($agent->type === 'knowledge_base') {
            $rules['sub_domain'] = [
                'required',
                'string',
                'min:3',
                'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('agents', 'sub_domain')->ignore($agent->id),
            ];
        }

        $validated = $request->validate($rules);

        $updateData = ['name' => $validated['name']];

        if ($agent->type === 'knowledge_base' && isset($validated['sub_domain'])) {
            $updateData['sub_domain'] = $validated['sub_domain'];
        }

        $agent->update($updateData);

        return redirect()->back()->with('success', 'Agent updated successfully');
    }
}
