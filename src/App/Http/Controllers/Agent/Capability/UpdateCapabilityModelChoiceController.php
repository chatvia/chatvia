<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Capability;

use App\Http\Requests\Agent\Capability\UpdateCapabilityModelChoiceRequest;
use Domain\Agent\Actions\Settings\UpdateAgentMediaModelChoiceAction;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Illuminate\Http\JsonResponse;

/**
 * Turns on or off whether chatters may pick the image or video model in the
 * hosted chat, for the Image generation and Video generation capabilities.
 * Any other capability has no such choice: a 404.
 */
class UpdateCapabilityModelChoiceController
{
    public function __invoke(UpdateCapabilityModelChoiceRequest $request, Agent $agent, UpdateAgentMediaModelChoiceAction $update): JsonResponse
    {
        $kind = $request->kind();
        abort_unless($kind instanceof GeneratedMediaKind, 404, __('Capability not found.'));

        $update->execute($agent, $kind, $request->boolean('enabled'));

        return response()->json(['ok' => true]);
    }
}
