<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Form;

use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Requests\Api\V1\Form\StoreApiFormRequest;
use App\Http\Requests\Api\V1\Form\UpdateApiFormRequest;
use App\Http\Resources\Api\V1\Form\FormData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Form\Actions\DeleteFormDefinitionAction;
use Domain\Form\Actions\EnableFormsCapabilityAction;
use Domain\Form\Actions\SaveFormDefinitionAction;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Support\Pagination\CursorPage;

/**
 * An agent's inline forms over the public API — the same Actions as the
 * dashboard's form builder. Mutations are gated by
 * `agent.sources.train.connectors`, exactly as the dashboard's Forms page;
 * `can.access.agent` turns a form of another agent into a 404.
 */
#[Group(name: 'Forms', weight: 100)]
class FormController
{
    /**
     * List forms
     *
     * The agent's inline forms, newest first, each with its submission count.
     * The list is paged with a cursor: to get the next page, send the
     * `next_cursor` of the response as `cursor`. On the last page
     * `next_cursor` is null.
     *
     * @return array{data: list<FormData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request, Agent $agent): array
    {
        $query = FormDefinition::query()
            ->forAgent($agent)
            ->withCount(['submissions as submissions_count' => fn (Builder $query): Builder => $query->where('status', FormSubmissionStatus::Submitted)]);

        $page = CursorPage::newestFirst($query, $request->perPage(), $request->cursor());

        return [
            'data' => array_map(FormData::fromModel(...), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Create form
     *
     * Defines a new inline form and switches the agent's Forms capability on,
     * so the agent can start offering it in chat.
     */
    public function store(StoreApiFormRequest $request, Agent $agent, SaveFormDefinitionAction $save, EnableFormsCapabilityAction $enableForms): JsonResponse
    {
        $form = $save->execute($agent, $request->toData());
        $enableForms->execute($agent);

        return response()->json(['data' => FormData::fromModel($form)], 201);
    }

    /**
     * Get form
     *
     * @return array{data: FormData}
     */
    public function show(Agent $agent, FormDefinition $form): array
    {
        $form->loadCount(['submissions as submissions_count' => fn (Builder $query): Builder => $query->where('status', FormSubmissionStatus::Submitted)]);

        return ['data' => FormData::fromModel($form)];
    }

    /**
     * Update form
     *
     * A field left out keeps its current value; `schema` is replaced whole
     * when sent. The slug never changes, so rendered forms keep resolving.
     *
     * @return array{data: FormData}
     */
    public function update(UpdateApiFormRequest $request, Agent $agent, FormDefinition $form, SaveFormDefinitionAction $save): array
    {
        $this->ensureEditable($form);

        $form = $save->execute($agent, $request->toData($form), $form);
        $form->loadCount(['submissions as submissions_count' => fn (Builder $query): Builder => $query->where('status', FormSubmissionStatus::Submitted)]);

        return ['data' => FormData::fromModel($form)];
    }

    /**
     * Delete form
     *
     * Deletes the form and its submissions. Removing the agent's last form
     * turns its Forms capability back off.
     */
    public function destroy(Agent $agent, FormDefinition $form, DeleteFormDefinitionAction $delete): Response
    {
        $this->ensureEditable($form);

        $delete->execute($agent, $form);

        return response()->noContent();
    }

    /** A capability's form (e.g. Calendar's bookings) is read-only. */
    private function ensureEditable(FormDefinition $form): void
    {
        abort_if($form->isManaged(), 403, __('This form is managed by a capability and cannot be changed.'));
    }
}
