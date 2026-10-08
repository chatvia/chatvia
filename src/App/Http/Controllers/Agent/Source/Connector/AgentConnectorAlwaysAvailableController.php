<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use App\Http\Requests\Agent\Connector\SetConnectorAlwaysAvailableRequest;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Actions\SetConnectorAlwaysAvailableAction;
use Domain\Integration\Mcp\Exceptions\AlwaysAvailableToolLimitException;
use Domain\Integration\Mcp\Exceptions\ConnectorNotAttachedException;
use Illuminate\Http\JsonResponse;

/**
 * Marks an attached connector "always on" for this agent (or turns it back
 * off). The action enforces the per-agent cap on always-shipped tools; a
 * breach comes back as a 422 the page can show verbatim.
 */
class AgentConnectorAlwaysAvailableController
{
    public function __invoke(
        SetConnectorAlwaysAvailableRequest $request,
        Agent $agent,
        string $slug,
        SetConnectorAlwaysAvailableAction $action,
    ): JsonResponse {
        $server = $agent->mcpServers()->where('slug', $slug)->first();

        abort_unless($server !== null, 404, 'Connector not attached to this agent.');

        try {
            $action->execute($agent, $server, $request->toData());
        } catch (AlwaysAvailableToolLimitException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (ConnectorNotAttachedException) {
            abort(404, 'Connector not attached to this agent.');
        }

        return response()->json(['ok' => true, 'always_available' => $request->toData()->always_available]);
    }
}
