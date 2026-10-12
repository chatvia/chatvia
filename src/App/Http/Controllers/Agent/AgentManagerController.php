<?php

namespace App\Http\Controllers\Agent;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The agent's home link: sends the user to the first agent page they may use.
 * Workspace owners and team owners land on the Playground; a teammate lands
 * on the first page their team permissions allow, the chat log otherwise.
 */
class AgentManagerController
{
    /**
     * Permission → page, in order of preference.
     *
     * @var array<string, string>
     */
    private const array PAGE_BY_PERMISSION = [
        'agent.setup' => 'agents.playground',

        // Analytics
        'agent.analytics.chats' => 'agents.analytics',
        'agent.analytics.sentiment' => 'agents.analytics.sentiment',
        'agent.analytics.tone' => 'agents.analytics.tone',
        'agent.analytics.topics' => 'agents.analytics.topics',
        'agent.analytics.sources' => 'agents.analytics.sources',

        'agent.chat.view' => 'agents.logs',

        // Sources (any training permission)
        'agent.sources.train.files' => 'agents.sources.files',
        'agent.sources.train.text' => 'agents.sources.text.index',
        'agent.sources.train.qa' => 'agents.sources.qa.index',
        'agent.sources.train.web' => 'agents.sources.web',
        'agent.sources.train.audio' => 'agents.sources.audio',
        'agent.sources.train.video' => 'agents.sources.video',

        'agent.leads.view' => 'agents.forms.index',

        'agent.topics.add' => 'agents.topics.index',
    ];

    public function __invoke(Request $request, Agent $agent): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $team = $user->currentOrPrimaryTeam();

        if ($user->isWorkspaceOwner() || ($team !== null && $user->ownsTeam($team))) {
            return redirect()->route('agents.playground', ['agent' => $agent]);
        }

        $granted = $team !== null ? $user->teamPermissions($team) : [];

        foreach (self::PAGE_BY_PERMISSION as $permission => $routeName) {
            if (in_array($permission, $granted, true)) {
                return redirect()->route($routeName, ['agent' => $agent]);
            }
        }

        return redirect()->route('agents.logs', ['agent' => $agent]);
    }
}
