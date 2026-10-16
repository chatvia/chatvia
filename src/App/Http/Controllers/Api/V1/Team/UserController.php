<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Auth\UserData;
use Dedoc\Scramble\Attributes\Group;
use Support\Pagination\CursorPage;

/**
 * The workspace's staff accounts (the owner and the teammates). The ids are
 * what `POST /teams/{team}/roles/{role}/users` and `POST /chatters/sign`
 * accept as `user_id`.
 */
#[Group(name: 'Teams', weight: 40)]
class UserController
{
    /**
     * List users
     *
     * The workspace's staff accounts, newest first. The list is paged with a
     * cursor: to get the next page, send the `next_cursor` of the response as
     * `cursor`. On the last page `next_cursor` is null.
     *
     * @return array{data: list<UserData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request): array
    {
        $page = CursorPage::newestFirst(
            $request->user()->workspace()->members(),
            $request->perPage(),
            $request->cursor(),
        );

        return [
            'data' => array_map(UserData::fromModel(...), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }
}
