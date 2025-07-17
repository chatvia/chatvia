<?php

namespace App\Http\Controllers\Agents\Settings;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentUpdateSettingsController
{
    public function __invoke(Agent $agent, string $group, Request $request)
    {
        $groups = collect(config('model-settings.defaults.'.Agent::class))
            ->keys()
            ->toArray();

        if (! in_array($group, $groups, true)) {
            abort(404);
        }

        $request->validate(config('model-settings.validation.'.$group));

        $settings = array_keys(config('model-settings.defaults.'.Agent::class.'.'.$group));

        $data = $request->only($settings);

        $keysToDelete = [];

        foreach ($data as $key => $value) {
            if (is_null($value)) {
                $keysToDelete[] = $key;
            } else {
                $agent->setSetting(
                    key: $key,
                    value: $value,
                    group: $group,
                );
            }
        }

        $agent->settingsDelete($group, $keysToDelete);
    }
}
