<?php

namespace App\Http\Controllers\Agent\Setup;

use App\Identity\Chatter\Services\ChatterAuthTokenService;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Deploy — the single home for putting an agent on the web. Surfaces the three
 * embed methods (Widget, Hosted Chat, Command Bar) side by side with their
 * install snippets and URLs. Appearance/behaviour for each surface is still
 * configured in Setup; this page only generates the code to ship.
 */
class AgentDeployController
{
    public function __invoke(Request $request, Agent $agent, ChatterAuthTokenService $authTokenService): Response
    {
        abort_if(! $request->user()->can('view', $agent), 404);

        return Inertia::render('agents/Deploy', [
            'agent' => AgentResourceData::fromModel($agent),
            // Private agents embed with a server-minted JWT — the snippets show a
            // `data-auth` placeholder and a "make it public" nudge.
            'authToken' => $agent->is_public ? null : $authTokenService->generateToken($agent, $request->user()),
            'embedScriptUrl' => config('chatvia.widget.script_url_embed'),
            // The Widget/Bar snippets reflect the surface's configured behaviour
            // (theme/language, trigger/placement) so the copied code matches Setup.
            'widgetSettings' => $agent->settings('widget'),
            'defaultWidget' => config('model-settings.defaults.'.Agent::class.'.widget'),
            'barSettings' => $agent->settings('bar'),
            'defaultBar' => config('model-settings.defaults.'.Agent::class.'.bar'),
        ]);
    }
}
