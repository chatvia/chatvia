<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;

/**
 * Permanently delete a chat member. Cascading FKs handle every artefact tied
 * to them — `agent_user` grants, `user_socials` identities, any open
 * `chatter_invitations` row, plus their chat sessions on the cascade chain.
 * Workspace owners / employees / admins are explicitly out of scope and
 * should be rejected before this Action is called.
 */
class DeleteChatterAction
{
    public function execute(User $chatter): void
    {
        $chatter->delete();
    }
}
