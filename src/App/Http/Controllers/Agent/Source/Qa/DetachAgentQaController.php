<?php

namespace App\Http\Controllers\Agent\Source\Qa;

use Domain\Agent\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Confirm-only endpoint for detaching a Q&A entry from this agent (the actual
 * detach is currently a no-op — Q&As live per-agent today and `destroy` deletes
 * them outright; this route exists so the UI can show a confirmation flow
 * symmetric with text/website detach).
 */
class DetachAgentQaController
{
    public function __invoke(Request $request, Agent $agent, int $id): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if($request->user()->cannot('update', $agent), 404);

        return response()->json(['message' => 'Q&A entry unlinked successfully']);
    }
}
