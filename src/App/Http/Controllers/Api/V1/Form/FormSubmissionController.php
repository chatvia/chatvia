<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Form;

use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Form\SubmissionData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Form\Models\FormDefinition;
use Support\Pagination\CursorPage;

/**
 * The captured submissions (leads) of one of an agent's forms, over the
 * public API. Gated by `agent.leads.view`; `can.access.agent` turns a form of
 * another agent into a 404.
 */
#[Group(name: 'Leads', weight: 110)]
class FormSubmissionController
{
    /**
     * List submissions
     *
     * The form's submitted entries, newest first. Pending rows (forms the
     * agent rendered but the visitor never sent) are left out. The list is
     * paged with a cursor: to get the next page, send the `next_cursor` of
     * the response as `cursor`. On the last page `next_cursor` is null.
     *
     * @return array{data: list<SubmissionData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request, Agent $agent, FormDefinition $form): array
    {
        $query = $form->submissions()->submitted()->with('conversation:id,external_id');

        $page = CursorPage::newestFirst($query, $request->perPage(), $request->cursor());

        return [
            'data' => array_map(SubmissionData::fromModel(...), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }
}
