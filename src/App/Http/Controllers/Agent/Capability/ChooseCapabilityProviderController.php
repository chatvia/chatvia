<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Capability;

use App\Http\Requests\Agent\Capability\ChooseCapabilityProviderRequest;
use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\ChooseCapabilityProviderAction;
use Domain\Integration\Connector\Exceptions\CapabilityProviderUnavailableException;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Illuminate\Http\JsonResponse;

/**
 * Pick which connector a capability works through for this agent (Calendar:
 * Calendly or Cal.com). One the capability cannot use, or the workspace has
 * not connected, comes back as a 422 with a message the page can show.
 */
class ChooseCapabilityProviderController
{
    public function __invoke(ChooseCapabilityProviderRequest $request, Agent $agent, string $slug, ConnectorCatalog $catalog, ChooseCapabilityProviderAction $choose): JsonResponse
    {
        $capability = $catalog->capabilities()->get($slug);
        abort_unless($capability !== null && $capability->worksThrough !== [], 404, __('Capability not found.'));

        try {
            $choose->execute($agent, $capability, $request->provider());
        } catch (CapabilityProviderUnavailableException $e) {
            return response()->json(['message' => __($e->getMessage())], 422);
        }

        return response()->json(['ok' => true]);
    }
}
