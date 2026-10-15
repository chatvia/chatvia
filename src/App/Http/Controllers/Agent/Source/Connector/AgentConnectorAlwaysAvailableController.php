<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use App\Http\Requests\Agent\Connector\SetConnectorAlwaysAvailableRequest;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Actions\Agent\SetConnectorAlwaysAvailableAction;
use Domain\Integration\Mcp\Exceptions\ConnectorNotAttachedException;
use Illuminate\Http\JsonResponse;

/**
 * Marks an attached connector "always on" for this agent (or turns it back
 * off), so its tools ship on every turn.
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

        abort_unless($server !== null, 404, __('Connector not attached to this agent.'));

        try {
            $action->execute($agent, $server, $request->toData());
        } catch (ConnectorNotAttachedException) {
            abort(404, __('Connector not attached to this agent.'));
        }

        return response()->json(['ok' => true, 'always_available' => $request->toData()->always_available]);
    }
}
