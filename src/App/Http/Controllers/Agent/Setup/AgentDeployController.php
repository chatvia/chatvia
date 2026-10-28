<?php

namespace App\Http\Controllers\Agent\Setup;

use App\Identity\Chatter\Services\ChatterAuthTokens;
use Domain\Agent\Data\Resources\Account\AgentDomainResourceData;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Data\Resources\Account\CustomDomainOptionsData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentDomain;
use Domain\Agent\Services\CustomDomainLookup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Support\License\Entitlements;

/**
 * Deploy — the single home for putting an agent on the web. Surfaces the three
 * embed methods (Widget, Hosted Chat, Command Bar) side by side with their
 * install snippets and URLs. Appearance/behaviour for each surface is still
 * configured in Setup; this page only generates the code to ship.
 */
class AgentDeployController
{
    public function __invoke(Request $request, Agent $agent, ChatterAuthTokens $authTokens, CustomDomainLookup $domains, Entitlements $entitlements): Response
    {
        $domain = $agent->domain()->first();

        return Inertia::render('dashboard/agents/pages/Deploy', [
            'agent' => AgentResourceData::fromModel($agent),
            // Private agents embed with a server-minted JWT — the snippets show a
            // `data-auth` placeholder and a "make it public" nudge.
            'authToken' => $agent->is_public ? null : $authTokens->generateToken($agent, $request->user()),
            'embedScriptUrl' => url(config('chatvia.widget.script_url_embed')),
            // The Widget/Bar snippets reflect the surface's configured behaviour
            // (theme/language, trigger/placement) so the copied code matches Setup.
            'widgetSettings' => $agent->settings('widget'),
            'defaultWidget' => config('model-settings.defaults.'.Agent::class.'.widget'),
            'barSettings' => $agent->settings('bar'),
            'defaultBar' => config('model-settings.defaults.'.Agent::class.'.bar'),
            // Per-surface on/off state, so each section can show an enable toggle.
            'surfaces' => $agent->settings('surfaces'),
            // Hosted Chat → Custom domain: the saved domain, if any, and
            // whether this install offers custom domains at all.
            'customDomain' => $domain instanceof AgentDomain ? AgentDomainResourceData::fromModel($domain) : null,
            'customDomainOptions' => new CustomDomainOptionsData(
                is_licensed: $entitlements->customDomains(),
                is_enabled: config('chatvia.custom_domains.enabled') === true,
                cname_target: $domains->cnameTarget(),
            ),
        ]);
    }
}
