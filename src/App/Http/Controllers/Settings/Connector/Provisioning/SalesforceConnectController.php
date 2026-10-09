<?php

namespace App\Http\Controllers\Settings\Connector\Provisioning;

use App\Http\Controllers\Concerns\BindsConnectorOAuthToSession;
use App\Http\Requests\Settings\Connector\SalesforceConnectRequest;
use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\OAuth\BeginBringYourOwnClientOAuthAction;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Connector\Services\Salesforce\SalesforceEndpointBuilder;
use Illuminate\Http\JsonResponse;

/**
 * Starts the Salesforce per-org OAuth + PKCE flow. Salesforce's MCP OAuth
 * client is registered per workspace (External Client App, no dynamic
 * registration), so the workspace supplies its own Consumer Key + org details
 * here; the endpoints + MCP URL are derived from them. Returns the provider's
 * authorize URL as JSON (`redirect_url`) for the modal to follow.
 */
class SalesforceConnectController
{
    use BindsConnectorOAuthToSession;

    public function __invoke(
        SalesforceConnectRequest $request,
        ConnectorCatalog $catalog,
        SalesforceEndpointBuilder $endpoints,
        BeginBringYourOwnClientOAuthAction $begin,
    ): JsonResponse {
        $connector = $catalog->find('salesforce');
        abort_unless($connector instanceof ConnectorData && $connector->requiresClientId && ! $connector->comingSoon, 404);

        /** @var User $user */
        $user = $request->user();

        try {
            $url = $begin->execute(
                $user,
                $connector,
                $endpoints->build($request->toData()),
                $this->connectorOAuthSessionBinding($request),
            );
        } catch (McpOAuthException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['redirect_url' => $url]);
    }
}
