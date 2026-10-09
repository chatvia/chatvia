<?php

namespace App\Http\Controllers\Agent\Form;

use App\Http\Requests\Agent\Form\FormSubmissionSelectionRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Form\Actions\ExportSubmissionsCsvAction;
use Domain\Form\Data\FormDefinitionData;
use Domain\Form\Data\SubmissionData;
use Domain\Form\Models\FormDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The submissions of one of the agent's forms: the list page, a CSV export
 * and a bulk delete. Access is gated by the route middleware; the form must
 * belong to the agent.
 */
class AgentSubmissionController
{
    /** The list page shows the newest submissions, up to this many. */
    private const int PAGE_LIMIT = 1000;

    public function index(Agent $agent, FormDefinition $form): InertiaResponse
    {
        $this->ensureFormOfAgent($agent, $form);

        return Inertia::render('agents/Forms/Submissions', [
            'agent' => AgentResourceData::fromModel($agent),
            'form' => FormDefinitionData::fromModel($form),
            'submissions' => SubmissionData::collect(
                $form->submissions()
                    ->submitted()
                    ->with('conversation')
                    ->latest('id')
                    ->limit(self::PAGE_LIMIT)
                    ->get(),
            ),
        ]);
    }

    public function export(FormSubmissionSelectionRequest $request, Agent $agent, FormDefinition $form, ExportSubmissionsCsvAction $export): Response
    {
        $this->ensureFormOfAgent($agent, $form);

        $filename = 'submissions-'.$form->slug.'-'.now()->format('Ymd-His').'.csv';

        return response($export->execute($form, $request->toData()), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function destroy(FormSubmissionSelectionRequest $request, Agent $agent, FormDefinition $form): RedirectResponse
    {
        $this->ensureFormOfAgent($agent, $form);

        $form->deleteSubmissions($request->toData());

        return back();
    }

    private function ensureFormOfAgent(Agent $agent, FormDefinition $form): void
    {
        abort_unless($form->agent_id === $agent->id, 404);
    }
}
