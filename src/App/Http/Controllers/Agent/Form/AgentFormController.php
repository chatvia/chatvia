<?php

namespace App\Http\Controllers\Agent\Form;

use App\Http\Requests\Agent\Form\SaveFormDefinitionRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Form\Actions\SaveFormDefinitionAction;
use Domain\Form\Data\FormDefinitionData;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Integration\Connector\Actions\Agent\DisableAgentCapabilityAction;
use Domain\Integration\Connector\Actions\Agent\EnableAgentCapabilityAction;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Services\McpException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentFormController
{
    public function index(Request $request, Agent $agent): Response
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        return Inertia::render('agents/Forms/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'forms' => FormDefinitionData::collect(
                FormDefinition::query()
                    ->forAgent($agent)
                    ->withCount(['submissions as submissions_count' => fn ($q) => $q->where('status', FormSubmission::STATUS_SUBMITTED)])
                    ->orderBy('name')
                    ->get(),
            ),
            'leadFields' => [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'phone', 'label' => 'Phone'],
                ['key' => 'job_title', 'label' => 'Job title'],
                ['key' => 'company', 'label' => 'Company'],
                ['key' => 'location', 'label' => 'Location'],
                ['key' => 'message', 'label' => 'Message'],
            ],
        ]);
    }

    public function store(
        SaveFormDefinitionRequest $request,
        Agent $agent,
        SaveFormDefinitionAction $action,
        EnableAgentCapabilityAction $enable,
        ConnectorCatalog $catalog,
    ): RedirectResponse {
        abort_if($request->user()->cannot('update', $agent), 404);

        $action->execute($agent, $request->toData());

        // A defined form is useless unless the agent can render it, so adding the
        // first form turns the Forms capability on (idempotent once enabled).
        $capability = $catalog->capabilities()->get('forms');
        if ($capability !== null && ! $agent->isFormsEnabled()) {
            try {
                $enable->execute($agent, $request->user(), $capability);
            } catch (McpException) {
                // Provisioning the builtin failed (remote tools/list); the form is
                // still saved and the owner can enable Forms manually.
            }
        }

        return back();
    }

    public function update(SaveFormDefinitionRequest $request, Agent $agent, FormDefinition $form, SaveFormDefinitionAction $action): RedirectResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_unless($form->agent_id === $agent->id, 404);

        $action->execute($agent, $request->toData(), $form);

        return back();
    }

    public function destroy(
        Request $request,
        Agent $agent,
        FormDefinition $form,
        DisableAgentCapabilityAction $disable,
        ConnectorCatalog $catalog,
    ): RedirectResponse {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_unless($form->agent_id === $agent->id, 404);

        $form->delete();

        // Removing the last form turns the capability back off — there's nothing
        // left for the agent to render.
        $capability = $catalog->capabilities()->get('forms');
        if ($capability !== null && FormDefinition::query()->forAgent($agent)->doesntExist()) {
            $disable->execute($agent, $capability);
        }

        return back();
    }
}
