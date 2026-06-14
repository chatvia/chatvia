<?php

namespace App\Http\Controllers\Analytics;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the owner-facing "Ask your data" analytics chat page. The page itself
 * holds the (ephemeral) transcript and streams turns from
 * {@see AnalyticsChatStreamController}.
 */
class AnalyticsChatController
{
    public function __invoke(): Response
    {
        return Inertia::render('AnalyticsChat');
    }
}
