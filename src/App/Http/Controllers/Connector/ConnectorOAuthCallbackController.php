<?php

namespace App\Http\Controllers\Connector;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Services\OAuth\McpOAuthException;
use Domain\Integration\Connector\Services\OAuth\McpOAuthFlow;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single global callback for every OAuth-backed connector. The state
 * parameter identifies the in-flight session — including whether it was
 * initiated from inside an agent (auto-attach intent) — so we land the
 * user back on the right page.
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
            return $this->bounceWithError(
                state: $state,
                message: $request->query('error_description', $upstreamError),
            );
        }

        if ($state === '' || $code === '') {
            return redirect('/')->with('error', 'OAuth callback is missing required parameters.');
        }

        // Peek the pending state BEFORE complete() consumes it so we know
        // where to redirect on success.
        $autoAttachAgent = $this->peekAutoAttachAgent($state);

        try {
            $server = $this->flow->complete($state, $code);
        } catch (McpOAuthException $e) {
            return $this->bounceWithError($state, $e->getMessage(), $autoAttachAgent);
        }

        $successMessage = $this->connectorNameFor($server).' connected.';

        return $autoAttachAgent
            ? redirect()->route('agents.sources.connectors.index', $autoAttachAgent)->with('success', $successMessage)
            : redirect()->route('settings.connectors.edit')->with('success', $successMessage);
    }

    private function bounceWithError(string $state, string $message, ?Agent $autoAttachAgent = null): RedirectResponse
    {
        $autoAttachAgent ??= $this->peekAutoAttachAgent($state);

        return $autoAttachAgent
            ? redirect()->route('agents.sources.connectors.index', $autoAttachAgent)->with('error', $message)
            : redirect()->route('settings.connectors.edit')->with('error', $message);
    }

    private function peekAutoAttachAgent(string $state): ?Agent
    {
        if ($state === '') {
            return null;
        }

        // get() doesn't consume — complete() will pull() it.
        $payload = cache()->get('oauth:mcp:state:'.$state);
        if (! is_array($payload) || empty($payload['auto_attach_agent_id'])) {
            return null;
        }

        return Agent::find((int) $payload['auto_attach_agent_id']);
    }

    private function connectorNameFor(AgentMcpServer $server): string
    {
        $entry = config('connectors.'.$server->connector_slug);

        return is_array($entry) && isset($entry['name']) ? (string) $entry['name'] : $server->name;
    }
}
