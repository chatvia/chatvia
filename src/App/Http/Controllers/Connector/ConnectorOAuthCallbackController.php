<?php

namespace App\Http\Controllers\Connector;

use App\Http\Requests\Connector\ConnectorOAuthCallbackRequest;
use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\OAuth\CompleteConnectorOAuthAction;
use Domain\Integration\Connector\Data\OAuth\ConnectorOAuthCallbackData;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\RedirectResponse;

/**
 * Single global callback for every OAuth-backed connector. The state
 * parameter identifies the in-flight flow; it is only accepted from the same
 * signed-in user and browser session that started it. Every outcome lands the
 * user back on the Connectors page with a flash message.
 */
class ConnectorOAuthCallbackController
{
    public function __invoke(
        ConnectorOAuthCallbackRequest $request,
        CompleteConnectorOAuthAction $complete,
        ConnectorCatalog $catalog,
    ): RedirectResponse {
        $upstreamError = $request->upstreamError();
        if ($upstreamError !== null) {
            return $this->bounceWithError($upstreamError);
        }

        $callback = $request->toData();
        if (! $callback instanceof ConnectorOAuthCallbackData) {
            return redirect('/')->with('error', __('OAuth callback is missing required parameters.'));
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $server = $complete->execute($user, $callback);
        } catch (McpOAuthException $e) {
            return $this->bounceWithError($e->getMessage());
        }

        return redirect()
            ->route('integrations.connectors.edit')
            ->with('success', __(':name connected.', ['name' => $this->displayName($server, $catalog)]));
    }

    private function bounceWithError(string $message): RedirectResponse
    {
        return redirect()->route('integrations.connectors.edit')->with('error', $message);
    }

    private function displayName(AgentMcpServer $server, ConnectorCatalog $catalog): string
    {
        return ($server->connector_slug ? $catalog->find($server->connector_slug)?->name : null) ?? $server->name;
    }
}
