<?php

namespace App\Http\Controllers\Analytics;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the owner-facing "Ask your data" analytics chat page. The page itself
 * holds the (ephemeral) transcript and streams turns from
 * {@see AnalyticsChatStreamController}. Owner-only, like the stream: a
 * teammate would get a page that cannot answer.
 */
class AnalyticsChatController
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->isWorkspaceOwner() === true, 403);

        return Inertia::render('dashboard/analytics/pages/AnalyticsChat');
    }
}
