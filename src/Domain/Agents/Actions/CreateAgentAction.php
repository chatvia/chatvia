<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;
use App\Prompts\PromptLoader;
use App\Teams\Models\Team;
use Domain\Agents\Models\Agent;
use Domain\Agents\Services\Agent as AgentService;
use Domain\Chat\Models\ChatTopic;
use Illuminate\Support\Facades\DB;

class CreateAgentAction
{
    /**
     * @param  array{name: string, type: string}  $attributes
     */
    public function execute(User $user, Team $team, array $attributes): Agent
    {
        return DB::transaction(function () use ($user, $team, $attributes): Agent {
            $externalId = AgentService::generateRandomExternalId();
            $agentOwnerId = $user->isManagedUser() ? $user->managed_by_user_id : $user->id;

            /** @var Agent $agent */
            $agent = Agent::create([
                'user_id' => $agentOwnerId,
                'team_id' => $team->id,
                'name' => $attributes['name'],
                'sub_domain' => $attributes['type'] === 'knowledge_base' ? $externalId : null,
                'system_instructions' => PromptLoader::load('knowledge/system'),
                'is_public' => false,
                'type' => $attributes['type'],
                'external_id' => $externalId,
            ]);

            $agent->setSetting(key: 'collect_leads', value: true, group: 'leads');
            $agent->topics()->attach(ChatTopic::pluck('id'));

            return $agent;
        });
    }
}
