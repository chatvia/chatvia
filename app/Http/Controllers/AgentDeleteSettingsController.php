<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentDeleteSettingsController
{
    public function __invoke(Agent $agent, string $group, Request $request)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $groups = collect(config('model-settings.defaults.'.Agent::class))
            ->keys()
            ->toArray();

        if (! in_array($group, $groups, true)) {
            abort(404);
        }

        $key = $request->input('key');

        if ($key) {
            $agent->settingsDelete($group, $key);
        }

    }
}
