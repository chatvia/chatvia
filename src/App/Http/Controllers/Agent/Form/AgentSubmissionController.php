<?php

namespace App\Http\Controllers\Agent\Form;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Form\Actions\ExportSubmissionsCsvAction;
use Domain\Form\Data\FormDefinitionData;
use Domain\Form\Data\SubmissionData;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AgentSubmissionController
{
    public function index(Request $request, Agent $agent, FormDefinition $form): InertiaResponse
    {
        $this->authorizeForm($request, $agent, $form);

        return Inertia::render('agents/Forms/Submissions', [
            'agent' => AgentResourceData::fromModel($agent),
            'form' => FormDefinitionData::fromModel($form),
            'submissions' => SubmissionData::collect(
                $form->submissions()
                    ->where('status', FormSubmission::STATUS_SUBMITTED)
                    ->with('conversation')
                    ->latest('id')
                    ->limit(1000)
                    ->get(),
            ),
        ]);
    }

    public function export(Request $request, Agent $agent, FormDefinition $form, ExportSubmissionsCsvAction $export): Response
    {
        $this->authorizeForm($request, $agent, $form);

        $ids = (array) $request->input('external_ids', []);
        $csv = $export->execute($form, array_values(array_filter($ids, 'is_string')));
        $filename = 'submissions-'.$form->slug.'-'.now()->format('Ymd-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function destroy(Request $request, Agent $agent, FormDefinition $form): RedirectResponse
    {
        $this->authorizeForm($request, $agent, $form, mutate: true);

        $ids = array_values(array_filter((array) $request->input('external_ids', []), 'is_string'));
        if ($ids !== []) {
            $form->submissions()->whereIn('external_id', $ids)->delete();
        }

        return back();
    }

    private function authorizeForm(Request $request, Agent $agent, FormDefinition $form, bool $mutate = false): void
    {
        abort_if($request->user()->cannot($mutate ? 'update' : 'view', $agent), 404);
        abort_unless($form->agent_id === $agent->id, 404);
    }
}
