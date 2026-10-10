<?php

namespace App\Http\Controllers\Agent\Slug;

use Domain\Agent\Services\AgentSlugGenerator;
use Domain\Llm\Usage\TokenUsageContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Suggests a free chat-address slug for a new or renamed agent (the
 * "generate" button). The suggestion's LLM tokens are attributed to the workspace.
 */
class GenerateAgentSlugController
{
    public function __invoke(Request $request, AgentSlugGenerator $generator, TokenUsageContext $usage): JsonResponse
    {
        $name = $usage->track(
            $request->user()->workspace()->id,
            fn (): string => $generator->generate(),
        );

        return response()->json(['name' => $name]);
    }
}
