<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Models\Agent;
use Domain\Connectors\Services\ConnectorCatalog;
use Domain\Connectors\Services\OAuth\McpOAuthException;
use Domain\Connectors\Services\OAuth\McpOAuthFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AgentConnectorOAuthStartController
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
        private readonly McpOAuthFlow $flow,
    ) {}

    public function __invoke(Request $request, Agent $agent, string $slug): RedirectResponse
    {
        abort_if($request->user()->cannot('update', $agent), 403);

        $connector = $this->catalog->find($slug);
        abort_unless($connector && $connector->authStrategy === 'oauth' && ! $connector->comingSoon, 404);

        try {
            $url = $this->flow->begin($agent, $connector);
        } catch (McpOAuthException $e) {
            return redirect()
                ->route('agents.sources.connectors.index', $agent)
                ->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }
}
