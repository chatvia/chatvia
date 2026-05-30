<?php

namespace App\Http\Controllers\Agents;

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentManagerController
{
    /**
     * Permission to route mapping - ordered by priority.
     */
    private array $permissionRoutes = [
        'agent.setup' => 'agents.playground',

        // Analytics
        'agent.analytics.chats' => 'agents.analytics',
        'agent.analytics.metrics' => 'agents.analytics.metrics',
        'agent.analytics.sentiment' => 'agents.analytics.sentiment',
        'agent.analytics.tone' => 'agents.analytics.tone',
        'agent.analytics.topics' => 'agents.analytics.topics',
        'agent.analytics.sources' => 'agents.analytics.sources',

        // Chat/History has highest priority
        'agent.chat.view' => 'agents.logs',

        // Sources (any training permission)
        'agent.sources.train.files' => 'agents.sources.files',
        'agent.sources.train.text' => 'agents.sources.text',
        'agent.sources.train.qa' => 'agents.sources.text',
        'agent.sources.train.web' => 'agents.sources.web',
        'agent.sources.train.audio' => 'agents.sources.audio',
        'agent.sources.train.video' => 'agents.sources.video',

        // Leads
        'agent.leads.view' => 'agents.leads',

        // Topics
        'agent.topics.add' => 'agents.topics.manage',

        // Setup/Config (lowest priority for staff)
    ];

    public function __invoke(Request $request, Agent $agent)
    {
        /** @var User $user */
        $user = $request->user();
        $team = current_team();

        // If user is the agent owner or team owner, go to default page
        if ($user->isWorkspace() || ($team && $user->ownsTeam($team))) {
            return $this->redirectToDefaultPage($agent);
        }

        // For employees, find the first page they have access to
        if ($team) {
            foreach ($this->permissionRoutes as $permission => $routeName) {
                if ($user->hasTeamPermission($team, $permission)) {
                    return redirect()->route($routeName, ['agent' => $agent]);
                }
            }
        }

        // Fallback: if no specific permission, redirect to chat logs
        return redirect()->route('agents.logs', ['agent' => $agent]);
    }

    private function redirectToDefaultPage(Agent $agent)
    {
        return redirect()->route('agents.playground', ['agent' => $agent]);
    }
}
