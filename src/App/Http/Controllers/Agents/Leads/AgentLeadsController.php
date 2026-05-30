<?php

namespace App\Http\Controllers\Agents\Leads;

use Domain\Agents\Actions\BulkDeleteAgentLeadsAction;
use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Data\Resources\LeadResourceData;
use Domain\Agents\Models\Agent;
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
                ->map(fn ($lead): LeadResourceData => LeadResourceData::fromModel($lead));

            return response()->json($leads);
        }

        $leadsConnectorAttached = $agent->mcpServers()
            ->where('slug', 'leads')
            ->where('enabled', true)
            ->exists();

        return Inertia::render('agents/Leads', [
            'agent' => AgentResourceData::fromModel($agent),
            'settings' => [
                'fields' => $agent->settings('leads'),
            ],
            'leadsConnectorAttached' => $leadsConnectorAttached,
            'connectorsUrl' => route('agents.sources.connectors.index', ['agent' => $agent->external_id]),
        ]);
    }

    public function destroy(Request $request, Agent $agent, BulkDeleteAgentLeadsAction $bulkDelete): RedirectResponse
    {
        abort_if($request->user()->cannot('delete', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.leads.view'), 403);

        $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
        ]);

        $bulkDelete->execute($agent, $request->input('lead_ids'));

        return back()->with('success', 'Selected leads have been deleted successfully.');
    }
}
