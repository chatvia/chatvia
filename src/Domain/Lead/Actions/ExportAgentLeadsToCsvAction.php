<?php

namespace Domain\Lead\Actions;

use Domain\Agent\Models\Agent;
use Domain\Lead\Models\Lead;
use Support\Exceptions\DomainValidationException;
use Illuminate\Database\Eloquent\Collection;

class ExportAgentLeadsToCsvAction
{
    /**
     * @param  array<int, int>  $leadIds
     * @return array{filename: string, csv: string}
     *
     * @throws DomainValidationException
     */
    public function execute(Agent $agent, array $leadIds): array
    {
        $leads = $agent->leads()
            ->whereIn('id', $leadIds)
            ->with(['conversation'])
            ->get();

        if ($leads->count() !== count($leadIds)) {
            throw new DomainValidationException('lead_ids', 'Some selected leads do not belong to this agent.');
        }

        return [
            'filename' => 'leads-'.$agent->name.'-'.now()->format('Y-m-d-H-i-s').'.csv',
            'csv' => $this->toCsv($leads),
        ];
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
