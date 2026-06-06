<?php

namespace App\Http\Controllers\Agent\Lead;

use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;

class AgentLeadSettingsController
{
    public function __invoke(Request $request, Agent $agent): void
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $request->validate([
            'fields' => ['required', 'array'],
            'fields.*.title' => ['required', 'string'],
            'fields.*.required' => ['required', 'boolean'],
        ]);

        $agent->setSetting('fields', (array) $request->input('fields', []), 'leads');
    }
}
