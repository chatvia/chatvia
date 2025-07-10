<?php

namespace App\Http\Controllers\Agents\Settings;

use App\Http\Requests\Agents\Settings\MessageHistorySettingsRequest;
use App\Models\Agent;

class AgentUpdateSettingsController
{
    public function __invoke(Agent $agent, string $group, MessageHistorySettingsRequest $request)
    {
        $groups = collect(config('model-settings.defaults.App\Models\Agent'))->keys()->toArray();

        if (! in_array($group, $groups, true)) {
            abort(404);
        }

        foreach ($request->validated() as $key => $value) {
            $agent->setSetting(
                key: $key,
                value: $value,
                group: $group,
            );
        }
    }
}
