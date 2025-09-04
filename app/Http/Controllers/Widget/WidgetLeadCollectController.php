<?php

namespace App\Http\Controllers\Widget;

use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetLeadCollectController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification)
    {
        $agentId = $request->header('x-agent-id');
        $threadId = $request->header('x-thread-id');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        $agent = current_agent_from_widget();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:255'],
            'company' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:255'],
            'user_agent' => ['sometimes', 'string', 'max:255'],
            'referrer' => ['nullable', 'string', 'max:255'],
        ]);

        $agent->leads()->create(
            array_merge($validated, [
                'session_id' => $request->header('x-session-id'),
                'conversation_id' => $threadId,
                'ip_address' => $request->ip(),
            ])
        );

        return response(status: 201);
    }
}
