<?php

namespace App\Http\Controllers\Agent\Lead;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Lead\Data\Resources\LeadResourceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentLeadController
{
    public function index(Request $request, Agent $agent): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            $perPage = (int) min((int) $request->integer('per_page', 50), 200);

            $leads = $agent->leads()
                ->with('conversation:id,external_id')
                ->orderByDesc('created_at')
                ->paginate($perPage)
                ->through(fn ($lead): LeadResourceData => LeadResourceData::fromModel($lead));

            return response()->json($leads);
        }

        $leadsConnectorAttached = $agent->mcpServers()
            ->where('slug', 'leads')
            ->where('is_enabled', true)
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

    public function destroy(Request $request, Agent $agent): RedirectResponse|JsonResponse
    {
        abort_if($request->user()->cannot('delete', $agent), 404);

        $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
        ]);

        $agent->leads()->whereIn('id', $request->input('lead_ids'))->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Selected leads have been deleted successfully.');
    }
}
