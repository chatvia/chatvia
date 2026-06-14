<?php

namespace App\Http\Controllers\Api\V1\Chatters;

use App\Identity\Chatter\Actions\DeleteChatterAction;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Delete one of your end-users (chatters). Removes the chatter account and
 * every artefact tied to it — agent grants, chat sessions, conversations and
 * uploaded attachments. To remove access to specific agents WITHOUT deleting
 * the chatter, use `DELETE /api/v1/chatters/{chatter}/agents` instead.
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
    public function __invoke(Request $request, string $chatter, DeleteChatterAction $action): Response
    {
        /** @var User $account */
        $account = $request->user();

        // Scope to the caller's workspace — only your own chatters resolve, so a
        // foreign or unknown id 404s rather than touching another account.
        $target = $account->workspace()->chatters()->where('external_id', $chatter)->firstOrFail();

        $action->execute($target);

        return response()->noContent();
    }
}
