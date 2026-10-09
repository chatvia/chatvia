<?php

namespace App\Http\Controllers\Agent\Settings;

use App\Http\Requests\Agent\Settings\DeleteAgentSettingRequest;
use App\Http\Requests\Agent\Settings\UpdateAgentSettingsRequest;
use Domain\Agent\Enums\AgentSettingsGroup;
use Domain\Agent\Models\Agent;

/**
 * The generic save / reset endpoint for the settings groups the Setup and
 * Deploy pages edit ({@see AgentSettingsGroup}); any other group is a 404.
 * Each write is a plain setting upsert, so it stays in the controller.
 */
class AgentSettingsController
{
    public function update(UpdateAgentSettingsRequest $request, Agent $agent, AgentSettingsGroup $group): void
    {
        foreach ($request->values() as $key => $value) {
            $agent->setSetting(key: $key, value: $value, group: $group->value);
        }
    }

    public function destroy(DeleteAgentSettingRequest $request, Agent $agent, AgentSettingsGroup $group): void
    {
        $agent->settingsDelete($group->value, $request->key());
    }
}
