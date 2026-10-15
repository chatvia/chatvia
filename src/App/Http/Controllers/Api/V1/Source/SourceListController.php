<?php

namespace App\Http\Controllers\Api\V1\Source;

use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Source\SourceData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Agent\Queries\AgentSourcePageQuery;

/**
 * Lists the files, texts and websites attached to an agent, whatever their
 * kind. Each entry includes its training status so customers can poll for
 * completion.
 */
#[Group(name: 'Sources', weight: 70)]
class SourceListController
{
    /**
     * List sources
     *
     * The agent's sources, newest first. The list is paged with a cursor: to
     * get the next page, send the `next_cursor` of the response as `cursor`.
     * On the last page `next_cursor` is null.
     *
     * @return array{data: list<SourceData>, next_cursor: string|null}
     */
    public function __invoke(CursorPageRequest $request, Agent $agent, AgentSourcePageQuery $sources): array
    {
        $page = $sources->get($agent, $request->perPage(), $request->cursor());

        return [
            'data' => array_map(SourceData::fromModel(...), $page->sources),
            'next_cursor' => $page->next_cursor,
        ];
    }
}
