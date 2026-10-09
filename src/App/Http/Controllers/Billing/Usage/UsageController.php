<?php

namespace App\Http\Controllers\Billing\Usage;

use Domain\Billing\Data\Resources\Usage\UsageResourceData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The usage page, and its JSON: how much of each plan limit the workspace
 * uses.
 */
class UsageController
{
    public function __invoke(Request $request): Response|UsageResourceData
    {
        if ($request->wantsJson()) {
            return UsageResourceData::fromUser($request->user());
        }

        return Inertia::render('Usage');
    }
}
