<?php

namespace App\Http\Controllers\Agents\Actions;

use App\Models\Agent;
use Inertia\Inertia;

class AgentActionsController
{
    public function __invoke(Agent $agent)
    {
        // Available action types
        $actionTypes = [
            'send_slack_message' => 'Send Slack ChatMessage',
            'send_email' => 'Send Email',
            'webhook' => 'Send Webhook',
            'create_ticket' => 'Create Support Ticket',
            'log_event' => 'Log Event',
            'save_to_database' => 'Save to Database',
        ];

        return Inertia::render('agents/Actions', [
            'agent' => $agent,
            'actionTypes' => $actionTypes,
        ]);
    }
}
