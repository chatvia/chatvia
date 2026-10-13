<?php

namespace App\Http\Controllers\Agent\Slug;

use Domain\Agent\Services\AgentSlugGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Suggests a free chat-address slug for a new or renamed agent (the
 * "generate" button). The suggestion's LLM tokens are attributed to the workspace.
 */
class GenerateAgentSlugController
{
    public function __invoke(Request $request, AgentSlugGenerator $generator): JsonResponse
    {
        $name = $generator->generate();

        return response()->json(['name' => $name]);
    }
}
