<?php

namespace App\Http\Controllers\Api\V1\Chatters;

use App\Identity\Chatter\Actions\DeleteChatterAction;
use App\Identity\Chatter\Models\Chatter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;

/**
 * Delete one of your end-users (chatters). Removes the chatter account and
 * every artefact tied to it — agent grants, chat sessions, conversations and
 * uploaded attachments. To remove access to specific agents WITHOUT deleting
 * the chatter, use `DELETE /api/v1/chatters/{chatter}/agents` instead.
 * `workspace.scope` turns another workspace's chatter into a 404.
 */
#[Group('Chatters', weight: 90)]
class DeleteChatterController
{
    /**
     * Delete chatter
     *
     * Permanently delete the chatter identified by `chatter` (its `id` from
     * `POST /api/v1/chatters`) and everything tied to them. This cannot be
     * undone.
     */
    public function __invoke(Chatter $chatter, DeleteChatterAction $action): Response
    {
        $action->execute($chatter);

        return response()->noContent();
    }
}
