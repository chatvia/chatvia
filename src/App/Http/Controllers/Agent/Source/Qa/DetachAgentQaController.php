<?php

namespace App\Http\Controllers\Agent\Source\Qa;

use Illuminate\Http\JsonResponse;

/**
 * Confirm-only endpoint for detaching a Q&A entry from this agent. Q&A entries
 * belong to one agent and `destroy` deletes them, so there is nothing to
 * detach; the route exists so the UI can offer the same confirmation flow as
 * for texts and websites. Agent access is checked by the route middleware.
 */
class DetachAgentQaController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['message' => __('Q&A entry unlinked successfully')]);
    }
}
