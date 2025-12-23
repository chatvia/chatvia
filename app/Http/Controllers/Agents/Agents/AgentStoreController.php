<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Http\Requests\Agents\CreateAgentRequest;
use App\Models\Agent;
use App\Models\ChatTopic;
use App\Models\Team;
use App\Models\User;
use App\Services\Ingestion\Ingestion;
use App\Services\Prompts\PromptLoader;
use App\Services\Subdomain;
use Illuminate\Support\Facades\DB;
use Throwable;

class AgentStoreController
{
    public function __invoke(CreateAgentRequest $request, Ingestion $ingestion, Subdomain $subdomain)
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Team $team */
        $team = current_team();

        if (! $team) {
            abort(404, 'No team found. Please select or create a team first.');
        }

        // Only team owners can create agents
        if (! $user->ownsTeam($team)) {
            abort(403, 'Only team owners can create agents.');
        }

        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            $externalId = \App\Services\Agents\Agent::generateRandomExternalId();

            $agentOwnerId = $user->isManagedUser() ? $user->managed_by_user_id : $user->id;

            /** @var Agent $agent */
            $agent = Agent::create([
                'user_id' => $agentOwnerId,
                'team_id' => $team->id,
                'name' => $validatedData['name'],
                'sub_domain' => $validatedData['type'] === 'knowledge_base' ? $externalId : null,
                'system_instructions' => PromptLoader::load('knowledge/system'),
                'is_public' => false,
                'type' => $validatedData['type'],
                'external_id' => $externalId,
            ]);

            $agent->setSetting(
                key: 'collect_leads',
                value: true,
                group: 'leads',
            );

            // Attach all existing topics to the new agent
            $topicIds = ChatTopic::pluck('id');
            $agent->topics()->attach($topicIds);

            DB::commit();

            return to_route('agents.sources', ['agent' => $agent, 'new' => true])
                ->with('success', 'Agent created successfully.');

        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            return back()
                ->withErrors(['general' => 'Failed to create agent. Please try again.'])
                ->withInput();
        }
    }
}
