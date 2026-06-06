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
                $this->csvSafe($lead->name),
                $this->csvSafe($lead->email),
                $this->csvSafe($lead->phone),
                $this->csvSafe($lead->job_title),
                $this->csvSafe($lead->company),
                $this->csvSafe($lead->location),
                $this->csvSafe($lead->message),
                $lead->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Neutralise CSV formula injection. Lead fields are visitor-controlled, so
     * a value beginning with = + - @ TAB or CR would execute as a formula when
     * the operator opens the export in Excel / Sheets. Prefix with an
     * apostrophe so the cell is treated as text.
     */
    private function csvSafe(?string $value): string
    {
        $value = (string) ($value ?? '');

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
