<?php

namespace App\Http\Controllers\Agent\Capability;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\DisableAgentCapabilityAction;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Illuminate\Http\JsonResponse;

/**
 * Turn a first-party capability off for this agent (the workspace keeps the
 * connected server for its other agents).
 */
class DisableAgentCapabilityController
{
    public function __invoke(Agent $agent, string $slug, ConnectorCatalog $catalog, DisableAgentCapabilityAction $disable): JsonResponse
    {
        $capability = $catalog->capabilities()->get($slug);
        abort_unless($capability !== null, 404, __('Capability not found.'));

        $disable->execute($agent, $capability);

        return response()->json(['ok' => true]);
    }
}
