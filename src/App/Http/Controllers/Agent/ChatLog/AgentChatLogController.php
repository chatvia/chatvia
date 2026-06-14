<?php

namespace App\Http\Controllers\Agent\ChatLog;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Billing\Enums\CapabilityEnum;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's chat-log page shell. Sub-resources (conversations,
 * messages, pulse, topics, countries) are served by dedicated controllers
 * in this namespace.
 */
class AgentChatLogController
{
    public function index(Request $request, Agent $agent): Response
    {
        return Inertia::render('agents/ChatLog/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'current_conversation_id' => $request->query('conversation_id'),
            'chatlogEnabled' => $agent->chatlogEnabled(),
            'handoffEnabled' => ($agent->settings('widget')['handoff_enabled'] ?? true)
                && ($agent->team?->hasCapability(CapabilityEnum::HANDOFF) ?? false),
        ]);
    }
}
