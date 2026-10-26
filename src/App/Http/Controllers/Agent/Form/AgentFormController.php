<?php

namespace App\Http\Controllers\Agent\Form;

use App\Http\Requests\Agent\Form\SaveFormDefinitionRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Form\Actions\DeleteFormDefinitionAction;
use Domain\Form\Actions\EnableFormsCapabilityAction;
use Domain\Form\Actions\SaveFormDefinitionAction;
use Domain\Form\Data\FormDefinitionData;
use Domain\Form\Data\FormPreviewAppearanceData;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Domain\Llm\Contracts\Transcriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's inline-form builder: the Forms page and create / update /
 * delete of a form. Access is gated by the route middleware; a form must
 * belong to the agent.
 */
class AgentFormController
{
    /** The submission fields a form field can be mapped to (lead-shaped readers use them). */
    private const array LEAD_FIELDS = [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'job_title' => 'Job title',
        'company' => 'Company',
        'location' => 'Location',
        'message' => 'Message',
    ];

    public function index(Agent $agent, Transcriber $transcriber): Response
    {
        return Inertia::render('agents/Forms/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'forms' => FormDefinitionData::collect(
                FormDefinition::query()
                    ->forAgent($agent)
                    ->withCount(['submissions as submissions_count' => fn (Builder $query): Builder => $query->where('status', FormSubmissionStatus::Submitted)])
                    ->orderBy('name')
                    ->get(),
            ),
            'leadFields' => array_map(
                fn (string $key, string $label): array => ['key' => $key, 'label' => __($label)],
                array_keys(self::LEAD_FIELDS),
                self::LEAD_FIELDS,
            ),
            'previewAppearance' => FormPreviewAppearanceData::fromAgent($agent, $transcriber->isEnabled()),
        ]);
    }

    public function store(SaveFormDefinitionRequest $request, Agent $agent, SaveFormDefinitionAction $save, EnableFormsCapabilityAction $enableForms): RedirectResponse
    {
        $save->execute($agent, $request->toData());
        $enableForms->execute($agent);

        return back();
    }

    public function update(SaveFormDefinitionRequest $request, Agent $agent, FormDefinition $form, SaveFormDefinitionAction $save): RedirectResponse
    {
        $this->ensureFormOfAgent($agent, $form);
        $this->ensureEditable($form);

        $save->execute($agent, $request->toData(), $form);

        return back();
    }

    public function destroy(Agent $agent, FormDefinition $form, DeleteFormDefinitionAction $delete): RedirectResponse
    {
        $this->ensureFormOfAgent($agent, $form);
        $this->ensureEditable($form);

        $delete->execute($agent, $form);

        return back();
    }

    private function ensureFormOfAgent(Agent $agent, FormDefinition $form): void
    {
        abort_unless($form->agent_id === $agent->id, 404);
    }

    /** A capability's form (e.g. Calendar's bookings) is read-only for the owner. */
    private function ensureEditable(FormDefinition $form): void
    {
        abort_if($form->isManaged(), 403, __('This form is managed by a capability and cannot be changed.'));
    }
}
