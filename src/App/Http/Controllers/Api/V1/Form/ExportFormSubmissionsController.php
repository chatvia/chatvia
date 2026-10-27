<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Form;

use App\Http\Requests\Api\V1\Form\ExportApiSubmissionsRequest;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Form\Actions\ExportSubmissionsCsvAction;
use Domain\Form\Models\FormDefinition;
use Illuminate\Http\Response;

/**
 * Downloads a form's submissions as CSV over the public API — the same
 * formula-injection-safe export as the dashboard. Gated by
 * `agent.leads.export`.
 */
#[Group(name: 'Leads', weight: 110)]
class ExportFormSubmissionsController
{
    /**
     * Export submissions as CSV
     *
     * A CSV with one column per form field plus the submitted-at timestamp —
     * the whole form, or only the submissions named by `ids`. The response is
     * `text/csv`, not JSON.
     */
    public function __invoke(ExportApiSubmissionsRequest $request, Agent $agent, FormDefinition $form, ExportSubmissionsCsvAction $export): Response
    {
        $filename = "submissions-{$form->slug}-".now()->format('Ymd-His').'.csv';

        return response($export->execute($form, $request->toData()->ids), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
