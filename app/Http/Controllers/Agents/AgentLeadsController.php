<?php

namespace App\Http\Controllers\Agents;

use App\Data\Resources\Agent\AgentResourceData;
use App\Data\Resources\Agent\LeadResourceData;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentLeadsController
{
    public function index(Request $request, Agent $agent): Response|JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.leads.view'), 403);

        if ($request->wantsJson()) {
            $leads = $agent->leads()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($lead) => LeadResourceData::fromModel($lead));

            return response()->json($leads);
        }

        return Inertia::render('agents/Leads', [
            'agent' => AgentResourceData::fromModel($agent),
            'settings' => [
                'fields' => $agent->settings('leads'),
            ],
        ]);
    }

    public function destroy(Request $request, Agent $agent): RedirectResponse
    {
        abort_if($request->user()->cannot('delete', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.leads.view'), 403);

        $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
        ]);

        $agent->leads()->whereIn('id', $request->input('lead_ids'))->delete();

        return back()->with('success', 'Selected leads have been deleted successfully.');
    }
}
