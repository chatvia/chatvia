<?php

namespace App\Http\Controllers\Api\V1\Agents;

use Domain\Agents\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @tags Agent Settings
 *
 * Read and patch agent settings groups. Each group has its own validation
 * schema defined in `config/model-settings.php`. Supported groups:
 *
 *   • appearance  — colors, logo, welcome message, chat window text
 *   • widget      — embed widget position, theme, sites
 *   • embed       — sidebar / theme switcher options for embedded view
 *   • leads       — lead form fields and copy
 */
class AgentSettingsController
{
    /**
     * Get settings
     *
     * Returns the merged defaults + saved values for a settings group.
     */
    public function show(Request $request, Agent $agent, string $group): JsonResponse
    {
        $this->assertGroup($group);

        return response()->json(['settings' => $agent->settings($group)]);
    }

    /**
     * Update settings
     *
     * Partially updates a settings group. Only keys present in the request
     * body are written; omitted keys keep their existing value or fall back
     * to the default.
     */
    public function update(Request $request, Agent $agent, string $group): JsonResponse
    {
        $this->assertGroup($group);

        $rules = config('model-settings.validation.'.$group);

        if (! is_array($rules)) {
            throw ValidationException::withMessages([
                'group' => "Settings group '$group' is not exposed in the API.",
            ]);
        }

        $request->validate($rules);

        $allowedKeys = array_keys(config('model-settings.defaults.'.Agent::class.'.'.$group, []));

        $payload = array_filter(
            $request->only($allowedKeys),
            fn ($value): bool => $value !== '' && $value !== null,
        );

        foreach ($payload as $key => $value) {
            $agent->setSetting(key: $key, value: $value, group: $group);
        }

        return response()->json(['settings' => $agent->settings($group)]);
    }

    private function assertGroup(string $group): void
    {
        $groups = array_keys(config('model-settings.defaults.'.Agent::class, []));

        if (! in_array($group, $groups, true)) {
            throw ValidationException::withMessages([
                'group' => "Unknown settings group '$group'. Allowed: ".implode(', ', $groups),
            ]);
        }
    }
}
