<?php

namespace App\Http\Controllers\Agents\Models;

use App\Enums\PermissionEnum;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentModelsShowController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $user = $request->user();

        if ($user->missingPermission(PermissionEnum::MODELS_VIEW)) {
            return back()->with('error', 'You do not have permission to view page');
        }

        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return Inertia::render('agents/Models', [
            'agent' => $agent,
        ]);
    }
}
