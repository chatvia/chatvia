<?php

namespace App\Http\Controllers\Agent\Settings;

use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;

class AgentSettingsController
{
    public function update(
        Request $request,
        Agent $agent,
        string $group,
    ): void {
        $this->assertGroup($group);

        $request->validate(config('model-settings.validation.'.$group));

        $settings = array_keys(config('model-settings.defaults.'.Agent::class.'.'.$group));

        $values = array_filter(
            $request->only($settings),
            fn ($value): bool => $value !== '' && $value !== null,
        );

        foreach ($values as $key => $value) {
            $agent->setSetting(key: $key, value: $value, group: $group);
        }
    }

    public function destroy(
        Request $request,
        Agent $agent,
        string $group,
    ): void {
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
