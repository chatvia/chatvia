<?php

namespace App\Http\Controllers\Agent\Capability;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\EnableAgentCapabilityAction;
use Domain\Integration\Connector\Exceptions\CapabilityRequiresConnectorException;
use Domain\Integration\Connector\Exceptions\CapabilityUnavailableException;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Exceptions\McpException;
use Illuminate\Http\JsonResponse;

/**
 * Turn a first-party capability on for this agent. The first enable in the
 * workspace connects it, which syncs its tools; a failure, or a connector the
 * capability needs that is not connected yet, or one the admin has turned
 * off, comes back as a 422 with a message the page can show.
 */
class EnableAgentCapabilityController
{
    public function __invoke(Agent $agent, string $slug, ConnectorCatalog $catalog, EnableAgentCapabilityAction $enable): JsonResponse
    {
        $capability = $catalog->capabilities()->get($slug);
        abort_unless($capability !== null, 404, __('Capability not found.'));

        try {
            $enable->execute($agent, $capability);
        } catch (McpException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (CapabilityUnavailableException) {
            return response()->json(['message' => __('Your admin has turned this off.')], 422);
        } catch (CapabilityRequiresConnectorException $e) {
            return response()->json(['message' => __('Connect :name first.', ['name' => $e->connectorNames])], 422);
        }

        return response()->json(['ok' => true]);
    }
}
