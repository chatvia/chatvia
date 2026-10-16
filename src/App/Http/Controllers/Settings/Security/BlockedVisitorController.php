<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Security;

use Domain\Security\Actions\UnblockVisitorAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Models\BlockedVisitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Removes a visitor block from the Security page's list, so the visitor can
 * chat again. Needs `account.security` (on the route); another workspace's
 * block is a 404 (`workspace.scope`).
 */
class BlockedVisitorController
{
    public function destroy(Request $request, BlockedVisitor $blockedVisitor, UnblockVisitorAction $unblock): RedirectResponse
    {
        $unblock->execute($blockedVisitor, SecurityActorData::forUser($request->user(), $request->ip(), $request->userAgent()));

        return back();
    }
}
