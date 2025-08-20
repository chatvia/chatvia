<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentUpdateSettingsController
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

        $request->validate(config('model-settings.validation.'.$group));

        $settings = array_keys(config('model-settings.defaults.'.Agent::class.'.'.$group));

        $requestData = $request->only($settings);

        $requestData = array_filter($requestData, function ($value) {
            return $value !== '' && $value !== null;
        });

        $keysToDelete = [];

        foreach ($requestData as $key => $value) {
            //            if (is_null($value)) {
            //                $keysToDelete[] = $key;
            //            } else {
            $agent->setSetting(
                key: $key,
                value: $value,
                group: $group,
            );
            //            }
        }

        $agent->settingsDelete($group, $keysToDelete);
    }
}
