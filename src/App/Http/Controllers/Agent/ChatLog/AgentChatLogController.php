<?php

namespace App\Http\Controllers\Agent\ChatLog;

use App\Admin\Settings\ToolRecording;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's chat-log page shell. Sub-resources (conversations,
 * messages, pulse, topics, countries) are served by dedicated controllers
 * in this namespace.
 */
class AgentChatLogController
{
    public function index(Request $request, Agent $agent, ToolRecording $tools): Response
    {
        return Inertia::render('agents/ChatLog/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'current_conversation_id' => $request->query('conversation_id'),
            'chatlogEnabled' => $agent->chatlogEnabled(),
            'handoffEnabled' => $agent->handoffEnabled(),
            // Where a reply's "trace" link points (Spectra's requests view,
            // filtered by trace id). Only for a viewer who may open /spectra —
            // an admin-panel session, or a workspace staff granted Spectra
            // access; null hides the link.
            'spectraTraceUrl' => $tools->spectraInstalled() && Gate::allows('viewSpectra')
                ? url(config('spectra.dashboard.path').'/requests')
                : null,
        ]);
    }
}
