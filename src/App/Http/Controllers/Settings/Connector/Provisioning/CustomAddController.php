<?php

namespace App\Http\Controllers\Settings\Connector\Provisioning;

use App\Http\Controllers\Concerns\BindsConnectorOAuthToSession;
use App\Http\Requests\Settings\Connector\AddCustomConnectorRequest;
use Domain\Integration\Connector\Actions\Account\AddCustomConnectorAction;
use Domain\Integration\Connector\Actions\OAuth\BeginCustomConnectorOAuthAction;
use Domain\Integration\Connector\Data\Custom\AddCustomConnectorData;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Exceptions\McpException;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Adds a custom MCP server (by URL) to the workspace. With the OAuth strategy
 * it returns `{ redirect_url }` for the browser to follow; with a token or no
 * auth it adds and syncs the server straight away.
 */
class CustomAddController
{
    use BindsConnectorOAuthToSession;

    public function __invoke(
        AddCustomConnectorRequest $request,
        AddCustomConnectorAction $add,
        BeginCustomConnectorOAuthAction $beginOAuth,
    ): JsonResponse {
        $data = $request->toData();

        return $data->auth_strategy === ConnectorAuthStrategy::Oauth
            ? $this->startOAuth($request, $data, $beginOAuth)
            : $this->add($data, $add);
    }

    private function startOAuth(AddCustomConnectorRequest $request, AddCustomConnectorData $data, BeginCustomConnectorOAuthAction $beginOAuth): JsonResponse
    {
        try {
            $redirectUrl = $beginOAuth->execute($data, $this->connectorOAuthSessionBinding($request));
        } catch (McpOAuthException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['redirect_url' => $redirectUrl]);
    }

    private function add(AddCustomConnectorData $data, AddCustomConnectorAction $add): JsonResponse
    {
        try {
            $result = $add->execute($data);
        } catch (Throwable $e) {
            $message = $e instanceof McpException
                ? $e->getMessage()
                : __('Could not connect to the MCP server: :reason', ['reason' => $e->getMessage()]);

            return response()->json(['message' => $message], 422);
        }

        return response()->json([
            'data' => AgentMcpServerData::fromModel($result->server),
            'tools_count' => $result->tools_count,
        ], 201);
    }
}
