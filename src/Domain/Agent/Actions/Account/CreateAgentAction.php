<?php

namespace Domain\Agent\Actions\Account;

use Domain\Agent\Data\Account\CreateAgentData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentLocator as AgentService;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new agent from the dashboard. The agent always belongs to the
 * workspace owner, also when a teammate creates it, so it stays with the
 * workspace if that teammate is removed. It starts private, gets a random
 * external id (also used as its first subdomain) and its own signing secret,
 * and is linked to every topic so conversation tagging works from the start.
 */
class CreateAgentAction
{
    public function execute(CreateAgentData $data): Agent
    {
        return DB::transaction(function () use ($data): Agent {
            $externalId = AgentService::generateRandomExternalId();
            $agentOwnerId = $data->user->belongsToWorkspace() ? $data->user->workspace_id : $data->user->id;

            /** @var Agent $agent */
            $agent = Agent::create([
                'user_id' => $agentOwnerId,
                'team_id' => $data->team->id,
                'name' => $data->name,
                'sub_domain' => $externalId,
                'is_public' => false,
                'external_id' => $externalId,
                'auth_secret' => Agent::generateAuthSecret(),
            ]);

            $agent->topics()->attach(Topic::pluck('id'));

            return $agent;
        });
    }
}
