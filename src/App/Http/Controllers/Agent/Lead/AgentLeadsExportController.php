<?php

namespace App\Http\Controllers\Agent\Lead;

use Domain\Agent\Models\Agent;
use Domain\Lead\Actions\ExportAgentLeadsToCsvAction;
use Domain\Membership\Team\Exceptions\InviteRejectedException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AgentLeadsExportController
{
    public function __invoke(Request $request, Agent $agent, ExportAgentLeadsToCsvAction $export): Response
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.leads.export'), 403);

        $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
        ]);

        try {
            $result = $export->execute($agent, $request->input('lead_ids'));
        } catch (InviteRejectedException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return response($result['csv'])
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="'.$result['filename'].'"');
    }
}
