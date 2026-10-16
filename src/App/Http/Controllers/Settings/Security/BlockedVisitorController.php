<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Security;

use Domain\Security\Actions\UnblockVisitorAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Models\BlockedVisitor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Removes a visitor block, so the visitor can chat again. Called with axios
 * from the blocked lists (Security page, an agent's Guardrails "Blocked" tab
 * and Security → Blocked), which drop the row themselves: it answers 204.
 * Needs `account.security` (on the route); another workspace's block is a
 * 404 (`workspace.scope`).
 */
class BlockedVisitorController
{
    public function destroy(Request $request, BlockedVisitor $blockedVisitor, UnblockVisitorAction $unblock): Response
    {
        $unblock->execute($blockedVisitor, SecurityActorData::forUser($request->user(), $request->ip(), $request->userAgent()));

        return response()->noContent();
    }
}
