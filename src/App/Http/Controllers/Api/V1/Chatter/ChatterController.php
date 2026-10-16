<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chatter;

use App\Http\Requests\Api\V1\Chatter\UpdateApiChatterRequest;
use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Auth\ChatterData;
use App\Identity\Chatter\Actions\UpdateChatterAccessAction;
use App\Identity\Chatter\Models\Chatter;
use Dedoc\Scramble\Attributes\Group;
use Support\Pagination\CursorPage;

/**
 * Read and update your chatters (end-users). Creation and deletion have
 * their own endpoints; `workspace.scope` turns another workspace's chatter
 * into a 404.
 */
#[Group(name: 'Chatters', weight: 90)]
class ChatterController
{
    /**
     * List chatters
     *
     * Your chatters, newest first. The list is paged with a cursor: to get
     * the next page, send the `next_cursor` of the response as `cursor`. On
     * the last page `next_cursor` is null.
     *
     * @return array{data: list<ChatterData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request): array
    {
        $page = CursorPage::newestFirst(
            $request->user()->workspace()->chatters()->with('messageLimit'),
            $request->perPage(),
            $request->cursor(),
        );

        return [
            'data' => array_map(ChatterData::fromModel(...), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Get chatter
     *
     * @return array{data: ChatterData}
     */
    public function show(Chatter $chatter): array
    {
        return ['data' => ChatterData::fromModel($chatter->loadMissing('messageLimit'))];
    }

    /**
     * Update chatter
     *
     * Updates the chatter's access controls: the monthly message cap and the
     * deactivation switch. A field left out keeps its current value.
     *
     * @return array{data: ChatterData}
     */
    public function update(UpdateApiChatterRequest $request, Chatter $chatter, UpdateChatterAccessAction $action): array
    {
        $action->execute($request->toData($chatter));

        return ['data' => ChatterData::fromModel($chatter->fresh(['messageLimit']))];
    }
}
