<?php

namespace App\Http\Controllers\Agent\Form;

use App\Http\Requests\Agent\Form\FormSubmissionSelectionRequest;
use Domain\Agent\Models\Agent;
use Domain\Form\Actions\ExportSubmissionsCsvAction;
use Domain\Form\Models\FormDefinition;
use Illuminate\Http\Response;

/**
 * Downloads the selected submissions of one of the agent's forms as a CSV
 * file. Access is gated by the route middleware; the form must belong to the
 * agent.
 */
class ExportFormSubmissionsController
{
    public function __invoke(FormSubmissionSelectionRequest $request, Agent $agent, FormDefinition $form, ExportSubmissionsCsvAction $export): Response
    {
        abort_unless($form->agent_id === $agent->id, 404);

        $filename = 'submissions-'.$form->slug.'-'.now()->format('Ymd-His').'.csv';

        return response($export->execute($form, $request->toData()), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
