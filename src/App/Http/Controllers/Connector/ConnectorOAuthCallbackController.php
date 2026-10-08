<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Concerns\BindsConnectorOAuthToSession;
use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Services\OAuth\McpOAuthException;
use Domain\Integration\Connector\Services\OAuth\McpOAuthFlow;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single global callback for every OAuth-backed connector. The state
 * parameter identifies the in-flight session; the flow only accepts it from
 * the same signed-in user and browser session that started it. Every outcome
 * lands the user back on the Connectors page with a flash message.
 */
class ConnectorOAuthCallbackController
{
    use BindsConnectorOAuthToSession;

    public function __construct(
        private readonly McpOAuthFlow $flow,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');
        $upstreamError = (string) $request->query('error', '');

        if ($upstreamError !== '') {
            return $this->bounceWithError((string) $request->query('error_description', $upstreamError));
        }

        if ($state === '' || $code === '') {
            return redirect('/')->with('error', 'OAuth callback is missing required parameters.');
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $server = $this->flow->complete($user, $this->connectorOAuthSessionBinding($request), $state, $code);
        } catch (McpOAuthException $e) {
            return $this->bounceWithError($e->getMessage());
        }

        return redirect()
            ->route('integrations.connectors.edit')
            ->with('success', $this->connectorNameFor($server).' connected.');
    }

    private function bounceWithError(string $message): RedirectResponse
    {
        return redirect()->route('integrations.connectors.edit')->with('error', $message);
    }

    private function connectorNameFor(AgentMcpServer $server): string
    {
        $entry = config('connectors.'.$server->connector_slug);

        return is_array($entry) && isset($entry['name']) ? (string) $entry['name'] : $server->name;
    }
}
