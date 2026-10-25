<?php

namespace App\Http\Controllers\Agent\Persona;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Data\Resources\AgentPromptPickerData;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's Persona page: its instructions and the workspace's saved
 * prompts to choose from. The prompt library goes only to someone who may set
 * the agent up or manage the library, since it is the workspace's, not this
 * agent's.
 */
class AgentShowPersonaController
{
    public function __invoke(Request $request, Agent $agent, TeamPermissionPolicy $permissions): Response
    {
        $agent->loadMissing('agentPrompt');

        /** @var User $user */
        $user = $request->user();

        $mayPick = $permissions->allowsOnAgent($user, $agent, 'agent.setup') || $permissions->allows($user, 'account.prompts');

        return Inertia::render('agents/Persona', [
            'agent' => AgentResourceData::fromModel($agent),
            'prompts' => $mayPick
                ? $user->workspace()->prompts()
                    ->latest()
                    ->get()
                    ->map(fn (AgentPrompt $prompt): AgentPromptPickerData => AgentPromptPickerData::fromModel($prompt))
                    ->all()
                : [],
        ]);
    }
}
