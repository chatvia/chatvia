<?php

namespace Domain\Agent\Actions\Account;

use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Data\Account\CreateApiAgentData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Actions\ResolveAgentPromptIdAction;
use Domain\Agent\Services\AgentLocator as AgentService;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Membership\Team\Actions\Team\ResolveOwnedTeamAction;
use Illuminate\Support\Facades\DB;
use Support\Enums\CreatedVia;

/**
 * Create an agent through the public API. Resolves the target team + (optional)
 * reusable prompt, then mints the agent and seeds the default topic catalog
 * inside a transaction.
 */
class CreateApiAgentAction
{
    public function __construct(
        private readonly ResolveOwnedTeamAction $resolveTeam,
        private readonly ResolveAgentPromptIdAction $resolvePromptId,
    ) {}

    public function execute(Workspace $workspace, CreateApiAgentData $data): Agent
    {
        $team = $this->resolveTeam->execute($workspace, $data->team_id);
        $agentPromptId = $this->resolvePromptId->execute($workspace, $data->prompt_id);

        return DB::transaction(function () use ($data, $workspace, $team, $agentPromptId): Agent {
            $externalId = AgentService::generateRandomExternalId();

            /** @var Agent $agent */
            $agent = Agent::query()->create([
                'workspace_id' => $workspace->id,
                'team_id' => $team->id,
                'name' => $data->name,
                'slug' => $externalId,
                'external_id' => $externalId,
                // A library prompt and a custom prompt are mutually exclusive.
                'system_instructions' => $agentPromptId === null ? $data->system_instructions : null,
                'agent_prompt_id' => $agentPromptId,
                'is_public' => $data->is_public,
                'metadata' => $data->metadata,
                'source' => CreatedVia::Api,
            ]);

            // System topics and this workspace's own, never another workspace's.
            $agent->topics()->attach(Topic::forWorkspace($workspace)->pluck('id'));

            return $agent;
        });
    }
}
