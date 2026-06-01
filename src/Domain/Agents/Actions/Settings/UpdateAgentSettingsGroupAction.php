<?php

namespace Domain\Agents\Actions\Settings;

use Domain\Agents\Data\Settings\UpdateAgentSettingsData;
use Domain\Agents\Models\Agent;

/**
 * Partial-write into a single agent settings group. Reads the allowed keys
 * from `config/model-settings.php` so the action can't write fields the
 * group doesn't declare, and skips empty/null values so the agent keeps its
 * existing value or falls back to the default.
 */
class UpdateAgentSettingsGroupAction
{
    public function execute(UpdateAgentSettingsData $data): void
    {
        $allowedKeys = array_keys(config('model-settings.defaults.'.Agent::class.'.'.$data->group, []));

        $writable = array_filter(
            array_intersect_key($data->payload, array_flip($allowedKeys)),
            fn ($value): bool => $value !== '' && $value !== null,
        );

        foreach ($writable as $key => $value) {
            $data->agent->setSetting(key: $key, value: $value, group: $data->group);
        }
    }
}
