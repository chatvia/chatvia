<?php

namespace App\Http\Controllers\Settings\Connectors;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Connectors\AddCustomConnectorRequest;
use Domain\Agents\Data\Resources\AgentMcpServerData;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Services\Tooling\Mcp\McpException;
use Domain\Connectors\Actions\ProvisionCustomMcpServerAction;
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

        /** @var User $user */
        $user = $request->user();
        $strategy = $request->string('auth_strategy')->toString();
        $name = $request->string('name')->toString();
        $url = $request->string('url')->toString();

        if ($strategy === 'oauth') {
            try {
                $redirectUrl = $this->oauthFlow->beginCustom(
                    user: $user,
                    name: $name,
                    slug: $this->uniqueCustomSlugForUser($user, $name),
                    mcpUrl: $url,
                );
            } catch (McpOAuthException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->json(['redirect_url' => $redirectUrl]);
        }

        try {
            $result = $provision->execute(new ProvisionCustomMcpServerData(
                user: $user,
                name: $name,
                url: $url,
                strategy: $strategy,
                token: $request->input('token'),
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
