<?php

namespace App\Http\Controllers\Agent\Capability;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\EnableAgentCapabilityAction;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Services\McpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnableAgentCapabilityController
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function __invoke(
        Request $request,
        Agent $agent,
        string $slug,
        EnableAgentCapabilityAction $enable,
    ): JsonResponse {
        abort_if($request->user()->cannot('update', $agent), 404);

        $capability = $this->catalog->capabilities()->get($slug);
        abort_unless($capability !== null, 404, 'Capability not found.');

        try {
            $enable->execute($agent, $request->user(), $capability);
        } catch (McpException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }
}
