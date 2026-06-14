<?php

namespace App\Http\Controllers\Settings\Connector\Provisioning;

use App\Http\Requests\Settings\Connector\SalesforceConnectRequest;
use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Connector\Services\OAuth\McpOAuthException;
use Domain\Integration\Connector\Services\OAuth\McpOAuthFlow;
use Domain\Integration\Connector\Services\Salesforce\SalesforceEndpointBuilder;
use Illuminate\Http\JsonResponse;

/**
 * Starts the Salesforce per-org OAuth + PKCE flow. Salesforce's MCP OAuth client
 * is registered per-workspace (External Client App, no DCR), so the workspace
 * supplies its own Consumer Key + org details here; we derive the endpoints +
 * MCP URL and hand off to {@see McpOAuthFlow::beginWithClient()}. Returns the
 * upstream authorize URL for the browser to redirect to (a full-page navigation,
 * so this is a JSON {redirect_url} the modal follows — not a server redirect).
 */
class SalesforceConnectController
{
    public function __invoke(
        SalesforceConnectRequest $request,
        ConnectorCatalog $catalog,
        McpOAuthFlow $flow,
        SalesforceEndpointBuilder $builder,
    ): JsonResponse {
        $connector = $catalog->find('salesforce');
        abort_unless($connector && $connector->requiresClientId && ! $connector->comingSoon, 404);

        $byo = $builder->build(
            clientId: $request->string('client_id')->toString(),
            clientSecret: $request->input('client_secret'),
            environment: $request->string('environment')->toString(),
            myDomain: $request->input('my_domain'),
            server: $request->string('server')->toString(),
        );

        /** @var User $user */
        $user = $request->user();

        try {
            $url = $flow->beginWithClient($user, $connector, $byo);
        } catch (McpOAuthException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['redirect_url' => $url]);
    }
}
