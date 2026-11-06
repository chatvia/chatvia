<?php

namespace App\Http\Controllers\Agent\ChatLog;

use App\Admin\Settings\ToolRecording;
use App\Identity\Workspace\Services\WorkspaceDashboards;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Handoff\HandoffAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spectra\Enums\DashboardSection;

/**
 * Renders the agent's chat-log page shell. Sub-resources (conversations,
 * messages, pulse, topics, countries) are served by dedicated controllers
 * in this namespace.
 */
class AgentChatLogController
{
    public function index(Request $request, Agent $agent, ToolRecording $tools, HandoffAvailability $handoff, WorkspaceDashboards $dashboards): Response
    {
        return Inertia::render('dashboard/agents/pages/ChatLog/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'current_conversation_id' => $request->query('conversation_id'),
            'chatlogEnabled' => $agent->chatlogEnabled(),
            'handoffEnabled' => $handoff->isOnFor($agent),
            // Where a reply's "trace" link points, with {trace} for its id; null
            // hides the link.
            'spectraTraceUrl' => $tools->spectraInstalled() ? $this->traceUrl($request, $dashboards) : null,
        ]);
    }

    /**
     * Staff open the trace in Spectra's requests view. The workspace's owner
     * opens it on the workspace's dashboard, when that shows traces.
     */
    private function traceUrl(Request $request, WorkspaceDashboards $dashboards): ?string
    {
        if (Gate::allows('viewSpectra')) {
            return url(config('spectra.dashboard.path').'/requests').'?trace_id={trace}';
        }

        $user = $request->user();
        $dashboard = $user?->isWorkspaceOwner() === true ? $dashboards->for($user->workspace()) : null;

        return $dashboard?->hasSection(DashboardSection::Traces) === true ? $dashboard->url().'/traces/{trace}' : null;
    }
}
