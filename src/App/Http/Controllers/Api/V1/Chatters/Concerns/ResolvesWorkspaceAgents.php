<?php

namespace App\Http\Controllers\Api\V1\Chatters\Concerns;

use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns the agent ids an API client sent into the workspace's own agents. Any
 * id that is not an agent of the workspace fails validation, so a foreign or
 * unknown id can never be granted or silently ignored.
 */
trait ResolvesWorkspaceAgents
{
    /**
     * @param  list<string>  $externalIds
     * @return Collection<int, Agent>
     *
     * @throws ValidationException
     */
    private function workspaceAgentsOrFail(Workspace $workspace, array $externalIds): Collection
    {
        /** @var Collection<int, Agent> $agents */
        $agents = $workspace->agents()->whereIn('external_id', $externalIds)->get();

        $missing = array_values(array_diff(array_unique($externalIds), $agents->pluck('external_id')->all()));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'agent_ids' => __('Unknown agent(s): :ids.', ['ids' => implode(', ', $missing)]),
            ]);
        }

        return $agents;
    }
}
