<?php

namespace Domain\Agent\Actions\Account;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Account\CreateApiAgentData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentLocator as AgentService;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Membership\Team\Actions\Team\ResolveOwnedTeamAction;
use Domain\Prompt\Actions\ResolveAgentPromptIdAction;
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
                'is_public' => $data->is_public,
                'metadata' => $data->metadata,
                'source' => CreatedVia::Api,
            ]);

            $agent->topics()->attach(Topic::pluck('id'));

            return $agent;
        });
    }
}
