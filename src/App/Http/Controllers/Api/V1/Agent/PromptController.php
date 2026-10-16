<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Agent\PromptData;
use Dedoc\Scramble\Attributes\Group;
use Support\Pagination\CursorPage;

/**
 * The workspace's prompt library (reusable system prompts, authored in the
 * dashboard). Listed here so an API client can discover the `prompt_id` the
 * agent endpoints accept.
 */
#[Group(name: 'Agents', weight: 10)]
class PromptController
{
    /**
     * List prompts
     *
     * The workspace's reusable prompts, newest first. Pass a prompt's `id` as
     * `prompt_id` when creating or updating an agent. The list is paged with
     * a cursor: to get the next page, send the `next_cursor` of the response
     * as `cursor`. On the last page `next_cursor` is null.
     *
     * @return array{data: list<PromptData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request): array
    {
        $page = CursorPage::newestFirst(
            $request->user()->workspace()->prompts(),
            $request->perPage(),
            $request->cursor(),
        );

        return [
            'data' => array_map(PromptData::fromModel(...), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }
}
