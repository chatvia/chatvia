<?php

namespace App\Http\Controllers\Agents\Leads;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AgentLeadsExportController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(! has_permission_to_agent($agent, 'agent.leads.export'), 403);

        $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
        ]);

        $leadIds = $request->input('lead_ids');

        $leads = $agent
            ->leads()
            ->whereIn('id', $leadIds)
            ->with(['conversation'])
            ->get();

        if ($leads->count() !== count($leadIds)) {
            throw ValidationException::withMessages([
                'lead_ids' => 'Some selected leads do not belong to this agent.',
            ]);
        }

        // Generate CSV content
        $csvContent = $this->generateCsv($leads);

        $filename = 'leads-'.$agent->name.'-'.now()->format('Y-m-d-H-i-s').'.csv';

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    private function generateCsv($leads): string
    {
        $headers = [
            'ID',
            'Name',
            'Email',
            'Phone',
            'Job Title',
            'Company',
            'Location',
            'Message',
            'Created At',
        ];

        $rows = [];
        $rows[] = $headers;

        foreach ($leads as $lead) {
            $rows[] = [
                $lead->id,
                $lead->name ?? '',
                $lead->email ?? '',
                $lead->phone ?? '',
                $lead->job_title ?? '',
                $lead->company ?? '',
                $lead->location ?? '',
                $lead->message ?? '',
                $lead->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $output = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }
}
