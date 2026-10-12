<?php

namespace App\Http\Controllers\Settings\Chatter\Concerns;

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;

/**
 * Looks up a chatter or an agent by external id inside the signed-in user's
 * workspace, so the workspace Chatters endpoints can never reach a chatter of
 * another owner or grant access to an agent outside the workspace.
 */
trait ResolvesWorkspaceChatters
{
    private function findChatter(Request $request, string $externalId): Chatter
    {
        /** @var Chatter $member */
        $member = $request->user()->workspace()
            ->chatters()
            ->where('external_id', $externalId)
            ->firstOrFail();

        return $member;
    }

    private function findWorkspaceAgent(Request $request, string $externalId): Agent
    {
        /** @var Agent $agent */
        $agent = $request->user()->workspace()
            ->agents()
            ->where('external_id', $externalId)
            ->firstOrFail();

        return $agent;
    }
}
