<?php

namespace App\Http\Controllers\Agent\Capability;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\DisableAgentCapabilityAction;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisableAgentCapabilityController
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function __invoke(Request $request, Agent $agent, string $slug, DisableAgentCapabilityAction $disable): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $capability = $this->catalog->capabilities()->get($slug);
        abort_unless($capability !== null, 404, 'Capability not found.');

        $disable->execute($agent, $capability);

        return response()->json(['ok' => true]);
    }
}
