<?php

namespace App\Http\Controllers\Agents\Leads;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentLeadsController extends Controller
{
    public function __invoke(Request $request, Agent $agent): Response
    {
        $leads = Lead::where('agent_id', $agent->id)
            ->with('agent')
            ->latest()
            ->get()
            ->groupBy('status');

        return Inertia::render('agents/Leads', [
            'agent' => $agent,
            'leads' => $leads,
            'statusColumns' => [
                'new' => 'New',
                'contacted' => 'Contacted',
                'qualified' => 'Qualified',
                'converted' => 'Converted',
            ],
        ]);
    }
}
