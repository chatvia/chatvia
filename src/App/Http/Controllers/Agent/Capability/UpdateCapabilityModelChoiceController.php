<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Capability;

use App\Http\Requests\Agent\Capability\UpdateCapabilityModelChoiceRequest;
use Domain\Agent\Actions\Settings\UpdateAgentMediaModelChoiceAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\JsonResponse;

/**
 * Turns on or off whether chatters may pick the image or video model in the
 * hosted chat, for the Image generation and Video generation capabilities.
 */
class UpdateCapabilityModelChoiceController
{
    public function __invoke(UpdateCapabilityModelChoiceRequest $request, Agent $agent, UpdateAgentMediaModelChoiceAction $update): JsonResponse
    {
        $update->execute($agent, $request->kind(), $request->boolean('enabled'));

        return response()->json(['ok' => true]);
    }
}
