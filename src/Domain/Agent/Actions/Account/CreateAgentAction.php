<?php

namespace Domain\Agent\Actions\Account;

use Domain\Agent\Data\Account\CreateAgentData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\Agent as AgentService;
use Domain\Topic\Models\ChatTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                'auth_secret' => 'cvsk_'.Str::random(56),
            ]);

            $agent->topics()->attach(ChatTopic::pluck('id'));

            return $agent;
        });
    }
}
