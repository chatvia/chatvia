<?php

namespace App\Http\Controllers\Agent\Setup;

use App\Http\Resources\Agent\SetupPageProps;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unified Setup for every surface. The same content settings (text, persona,
 * starters, model, branding) apply across Hosted Chat, Widget, and Bar — the
 * surface-tab in the Vue page is purely a preview-switcher. The page does
 * receive each surface's behaviour-only settings (Widget position/sites,
 * Bar trigger/placement, Hosted Chat iframe options) because the Behavior +
 * Embed sections swap their fields depending on the active preview tab.
 */
class SetupController
{
    public function __invoke(Request $request, Agent $agent, SetupPageProps $props): Response
    {
        return Inertia::render('dashboard/agents/pages/setup/Setup', $props->for($agent, $request->user()));
    }
}
