<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentSettingsController
{
    public function update(Request $request, Agent $agent, string $group): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $this->assertGroup($group);

        $request->validate(config('model-settings.validation.'.$group));

        $settings = array_keys(config('model-settings.defaults.'.Agent::class.'.'.$group));

        $requestData = array_filter(
            $request->only($settings),
            fn ($value) => $value !== '' && $value !== null,
        );

        foreach ($requestData as $key => $value) {
            $agent->setSetting(key: $key, value: $value, group: $group);
        }

        // NOTE: prior implementation called settingsDelete($group, []) here with
        // an empty array, which was a no-op; intentionally dropped.
    }

    public function destroy(Request $request, Agent $agent, string $group): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $this->assertGroup($group);

        $key = $request->input('key');

        if ($key) {
            $agent->settingsDelete($group, $key);
        }
    }

    private function assertGroup(string $group): void
    {
        $groups = collect(config('model-settings.defaults.'.Agent::class))->keys()->toArray();

        if (! in_array($group, $groups, true)) {
            abort(404);
        }
    }
}
