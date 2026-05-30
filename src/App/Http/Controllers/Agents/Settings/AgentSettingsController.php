<?php

namespace App\Http\Controllers\Agents\Settings;

use Domain\Agents\Actions\DeleteAgentSettingAction;
use Domain\Agents\Actions\UpdateAgentSettingsAction;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentSettingsController
{
    public function update(
        Request $request,
        Agent $agent,
        string $group,
        UpdateAgentSettingsAction $updateSettings,
    ): void {
        abort_if($request->user()->cannot('view', $agent), 404);

        $this->assertGroup($group);

        $request->validate(config('model-settings.validation.'.$group));

        $settings = array_keys(config('model-settings.defaults.'.Agent::class.'.'.$group));

        $values = array_filter(
            $request->only($settings),
            fn ($value): bool => $value !== '' && $value !== null,
        );

        $updateSettings->execute($agent, $group, $values);
    }

    public function destroy(
        Request $request,
        Agent $agent,
        string $group,
        DeleteAgentSettingAction $deleteSetting,
    ): void {
        abort_if($request->user()->cannot('view', $agent), 404);

        $this->assertGroup($group);

        $key = $request->input('key');

        if ($key) {
            $deleteSetting->execute($agent, $group, $key);
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
