<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Http\Requests\Api\V1\Agents\UpdateAgentSettingsRequest;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Actions\Settings\UpdateAgentSettingsGroupAction;
use Domain\Agents\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Read and patch agent settings groups.
 */
#[Group(name: 'Agents', weight: 10)]
class AgentSettingsController
{
    /**
     * Get settings — merged defaults + saved values for one group.
     */
    public function show(Request $request, Agent $agent, string $group): JsonResponse
    {
        $this->assertGroup($group);

        return response()->json(['data' => $agent->settings($group)]);
    }

    /**
     * Update settings — partial write into a single group.
     */
    public function update(UpdateAgentSettingsRequest $request, Agent $agent, string $group, UpdateAgentSettingsGroupAction $update): JsonResponse
    {
        $update->execute($request->toData($agent));

        return response()->json(['data' => $agent->settings($group)]);
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
