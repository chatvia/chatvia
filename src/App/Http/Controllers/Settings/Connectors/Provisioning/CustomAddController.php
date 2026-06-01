<?php

namespace App\Http\Controllers\Settings\Connectors\Provisioning;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Connectors\AddCustomConnectorRequest;
use Domain\Agents\Data\Resources\Mcp\AgentMcpServerData;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Services\Tooling\Mcp\McpException;
use Domain\Connectors\Actions\Mcp\ProvisionCustomMcpServerAction;
use Domain\Connectors\Data\ProvisionCustomMcpServerData;
use Domain\Connectors\Services\OAuth\McpOAuthException;
use Domain\Connectors\Services\OAuth\McpOAuthFlow;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Adds a user-supplied (custom) MCP server at the account level. For OAuth strategy
 * returns `{ redirect_url }` so the UI can navigate; for token / no-auth, provisions
 * immediately via the Action.
 */
class CustomAddController extends Controller
{
    public function __construct(
        private readonly McpOAuthFlow $oauthFlow,
    ) {}

    public function __invoke(
        AddCustomConnectorRequest $request,
        ProvisionCustomMcpServerAction $provision,
    ): JsonResponse {
        abort_unless(has_team_permission('account.connectors'), 404);

        $data = $request->toData();

        if ($data->auth_strategy === 'oauth') {
            try {
                $redirectUrl = $this->oauthFlow->beginCustom(
                    user: $data->user,
                    name: $data->name,
                    slug: $this->uniqueCustomSlugForUser($data->user, $data->name),
                    mcpUrl: $data->url,
                );
            } catch (McpOAuthException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->json(['redirect_url' => $redirectUrl]);
        }

        try {
            $result = $provision->execute(new ProvisionCustomMcpServerData(
                user: $data->user,
                name: $data->name,
                url: $data->url,
                strategy: $data->auth_strategy,
                token: $data->token,
            ));
        } catch (Throwable $e) {
            $message = $e instanceof McpException
                ? $e->getMessage()
                : 'Could not connect to the MCP server: '.$e->getMessage();

            return response()->json(['message' => $message], 422);
        }

        return response()->json([
            'data' => AgentMcpServerData::fromModel($result->server),
            'tools_count' => $result->tools_count,
        ], 201);
    }

    private function uniqueCustomSlugForUser(User $user, string $name): string
    {
        $base = AgentMcpServer::makeSlug($name);
        $slug = $base;
        $i = 2;

        while ($user->mcpServers()->where('slug', $slug)->exists()) {
            $slug = substr($base, 0, 38).'_'.$i;
            $i++;
        }

        return $slug;
    }
}
