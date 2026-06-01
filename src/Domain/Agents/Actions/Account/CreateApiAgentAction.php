<?php

namespace Domain\Agents\Actions\Account;

use App\Auth\Models\User;
use Domain\Agents\Data\Account\CreateApiAgentData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Services\Agent as AgentService;
use Domain\Chat\Models\ChatTopic;
use Domain\Teams\Actions\Team\ResolveOwnedTeamAction;
use Illuminate\Support\Facades\DB;

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

    public function execute(User $owner, CreateApiAgentData $data): Agent
    {
        $team = $this->resolveTeam->execute($owner, $data->team_id);
        $agentPromptId = $this->resolvePromptId->execute($owner, $data->prompt_id);

        return DB::transaction(function () use ($data, $owner, $team, $agentPromptId): Agent {
            $externalId = AgentService::generateRandomExternalId();

            /** @var Agent $agent */
            $agent = Agent::create([
                'user_id' => $owner->id,
                'team_id' => $team->id,
                'name' => $data->name,
                'sub_domain' => $externalId,
                'external_id' => $externalId,
                // A library prompt and a custom prompt are mutually exclusive.
                'system_instructions' => $agentPromptId === null ? $data->system_instructions : null,
                'agent_prompt_id' => $agentPromptId,
                'is_public' => false,
                'metadata' => $data->metadata,
                'source' => 'api',
            ]);

            $agent->topics()->attach(ChatTopic::pluck('id'));

            return $agent;
        });
    }
}
