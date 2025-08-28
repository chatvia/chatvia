<?php

namespace App\Http\Controllers\Agents\Actions;

use App\Features\Actions as ActionsFeature;
use App\Models\Agent;
use Inertia\Inertia;
use Laravel\Pennant\Feature;

class AgentActionsController
{
    public function __invoke(Agent $agent)
    {
        if (! Feature::active(ActionsFeature::class)) {
            return back()->with('error', 'Actions feature is not enabled');
        }

        // Available action types
        $actionTypes = [
            'send_slack_message' => 'Send Slack Message',
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
