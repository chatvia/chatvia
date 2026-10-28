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
 * external id (also used as its first chat slug) and its own signing secret,
 * and is linked to every topic its workspace can use (the system topics and
 * the workspace's own) so conversation tagging works from the start — never to
 * another workspace's custom topics.
 */
class CreateAgentAction
{
    public function execute(CreateAgentData $data): Agent
    {
        return DB::transaction(function () use ($data): Agent {
            $workspace = $data->user->workspace();

            $externalId = AgentService::generateRandomExternalId();

            /** @var Agent $agent */
            $agent = Agent::query()->create([
                'workspace_id' => $workspace->id,
                'team_id' => $data->team->id,
                'name' => $data->name,
                'slug' => $externalId,
                'is_public' => false,
                'external_id' => $externalId,
                'auth_secret' => Agent::generateAuthSecret(),
            ]);

            $agent->topics()->attach(Topic::forWorkspace($workspace)->pluck('id'));

            return $agent;
        });
    }
}
