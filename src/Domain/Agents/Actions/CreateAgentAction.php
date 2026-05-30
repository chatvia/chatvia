<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;
use App\Teams\Models\Team;
use Domain\Agents\Models\Agent;
use Domain\Agents\Services\Agent as AgentService;
use Domain\Chat\Models\ChatTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAgentAction
{
    /**
     * @param  array{name: string}  $attributes
     */
    public function execute(User $user, Team $team, array $attributes): Agent
    {
        return DB::transaction(function () use ($user, $team, $attributes): Agent {
            $externalId = AgentService::generateRandomExternalId();
            $agentOwnerId = $user->belongsToWorkspace() ? $user->workspace_id : $user->id;

            /** @var Agent $agent */
            $agent = Agent::create([
                'user_id' => $agentOwnerId,
                'team_id' => $team->id,
                'name' => $attributes['name'],
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
