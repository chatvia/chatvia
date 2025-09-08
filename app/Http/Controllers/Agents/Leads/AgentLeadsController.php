<?php

namespace App\Http\Controllers\Agents\Leads;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentLeadsController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $leads = $agent->leads()
            ->with(['conversation'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'job_title' => $lead->job_title,
                    'company' => $lead->company,
                    'location' => $lead->location,
                    'message' => $lead->message,
                    'referrer_url' => $lead->referrer_url,
                    'page_url' => $lead->page_url,
                    'locale' => $lead->locale,
                    'ip_address' => $lead->ip_address,
                    'user_agent' => $lead->user_agent,
                    'created_at' => $lead->created_at,
                    'conversation_id' => $lead->chat_conversation_id,
                    'conversation' => $lead->conversation,
                ];
            });

        return Inertia::render('agents/Leads', [
            'agent' => $agent,
            'leads' => $leads,
            'settings' => [
                'fields' => $agent->settings('leads'),
            ],
        ]);
    }
}
