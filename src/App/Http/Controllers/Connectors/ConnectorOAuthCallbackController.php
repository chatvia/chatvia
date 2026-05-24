<?php

namespace App\Http\Controllers\Connectors;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Services\OAuth\McpOAuthException;
use Domain\Connectors\Services\OAuth\McpOAuthFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single global callback for every OAuth-backed connector. The state
 * parameter identifies the in-flight session — including which agent it
 * belongs to — so we don't need per-agent callback URLs (which would
 * blow up the DCR registration).
 */
class ConnectorOAuthCallbackController
{
    public function __construct(
        private readonly McpOAuthFlow $flow,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');
        $upstreamError = (string) $request->query('error', '');

        if ($upstreamError !== '') {
            return $this->bounceToConnectorsWithError(
                state: $state,
                message: $request->query('error_description', $upstreamError),
            );
        }

        if ($state === '' || $code === '') {
            return redirect('/')->with('error', 'OAuth callback is missing required parameters.');
        }

        try {
            $server = $this->flow->complete($state, $code);
        } catch (McpOAuthException $e) {
            return $this->bounceToConnectorsWithError($state, $e->getMessage());
        }

        $agent = Agent::find($server->agent_id);
        if (! $agent) {
            return redirect('/')->with('error', 'Could not locate the agent for this connection.');
        }

        return redirect()
            ->route('agents.sources.connectors.index', $agent)
            ->with('success', $this->connectorNameFor($server).' connected.');
    }

    /**
     * On failure we still want to land the user back on the connectors
     * page for the right agent. We try to recover the agent from the
     * (now-consumed) state payload if possible; otherwise fall back to
     * a generic redirect.
     */
    private function bounceToConnectorsWithError(string $state, string $message): RedirectResponse
    {
        if ($state !== '') {
            $payload = cache()->pull('oauth:mcp:state:'.$state);

            if (is_array($payload) && isset($payload['agent_id'])) {
                $agent = Agent::find((int) $payload['agent_id']);
                if ($agent) {
                    return redirect()
                        ->route('agents.sources.connectors.index', $agent)
                        ->with('error', $message);
                }
            }
        }

        return redirect('/')->with('error', $message);
    }

    private function connectorNameFor(AgentMcpServer $server): string
    {
        $entry = config('connectors.'.$server->connector_slug);

        return is_array($entry) && isset($entry['name']) ? (string) $entry['name'] : $server->name;
    }
}
