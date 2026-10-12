<?php

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Data\Sessions\LoginData;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Hash;

/**
 * Checks a chatter's password for an agent's hosted chat (/chat/login).
 *
 * The chatter is looked up inside the agent's workspace only, by email without
 * case: the same email can be a chatter in several workspaces, and staff
 * accounts are not in this table at all. Owners, teammates and admins open a
 * private agent's chat from the dashboard (a one-time sign-in token) or the
 * emailed sign-in link, which runs their two-factor check.
 *
 * A wrong password, an unknown email, a deactivated chatter and a chatter
 * without access to the agent all fail the same way, so the form never tells
 * which one it was. The caller signs the chatter in.
 */
class LoginChatterToAgentAction
{
    public function execute(LoginData $data, Agent $agent): ?Chatter
    {
        $chatter = Chatter::query()
            ->where('workspace_id', $agent->workspace_id)
            ->whereRaw('lower(email) = ?', [strtolower(trim($data->email))])
            ->first();

        $hash = $chatter?->password;

        if (! is_string($hash) || $hash === '') {
            // Same cost as a real check, so an unknown email is not faster.
            Hash::make($data->password);

            return null;
        }

        if (! Hash::check($data->password, $hash)) {
            return null;
        }

        if ($chatter->isDeactivated() || ! $chatter->canAccessAgent($agent)) {
            return null;
        }

        return $chatter;
    }
}
