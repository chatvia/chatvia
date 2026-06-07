<?php

namespace App\Http\Controllers\Concerns;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;

/**
 * Resolve one of the authenticated account's agents by external id, 404-ing on
 * anything the account doesn't own. Shared by the account-level
 * function/connector assignment controllers so this tenant-scoping check lives
 * in exactly one place and can't drift between near-identical copies.
 */
trait ResolvesAccountAgent
{
    protected function resolveAccountAgent(Request $request, string $externalId): Agent
    {
        /** @var User $user */
        $user = $request->user();
        $agent = $user->agents->firstWhere('external_id', $externalId);

        abort_if($agent === null, 404);

        return $agent;
    }
}
