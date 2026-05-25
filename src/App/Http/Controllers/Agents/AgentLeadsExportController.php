<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Lead;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AgentLeadsExportController
{
    public function __invoke(Request $request, Agent $agent): Response
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.leads.export'), 403);

        $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
        ]);

        $leadIds = $request->input('lead_ids');

        $leads = $agent->leads()
            ->whereIn('id', $leadIds)
            ->with(['conversation'])
            ->get();

        if ($leads->count() !== count($leadIds)) {
            throw ValidationException::withMessages([
                'lead_ids' => 'Some selected leads do not belong to this agent.',
            ]);
        }

        $filename = 'leads-'.$agent->name.'-'.now()->format('Y-m-d-H-i-s').'.csv';

        return response($this->toCsv($leads))
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /**
     * @param  Collection<int, Lead>  $leads
     */
    private function toCsv(Collection $leads): string
    {
        $output = fopen('php://temp', 'r+');

        fputcsv($output, [
            'ID', 'Name', 'Email', 'Phone', 'Job Title', 'Company', 'Location', 'Message', 'Created At',
        ]);

        foreach ($leads as $lead) {
            fputcsv($output, [
                $lead->id,
                $lead->name ?? '',
                $lead->email ?? '',
                $lead->phone ?? '',
                $lead->job_title ?? '',
                $lead->company ?? '',
                $lead->location ?? '',
                $lead->message ?? '',
                $lead->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
