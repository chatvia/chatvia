<?php

namespace App\Http\Controllers\Agent\SubDomain;

use Domain\Agent\Services\SubdomainGenerator;
use Domain\Llm\Usage\TokenUsageContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Suggests a free subdomain for a new or renamed agent (the "generate"
 * button). The suggestion's LLM tokens are attributed to the workspace.
 */
class AgentsGenerateRandomSubDomainController
{
    public function __invoke(Request $request, SubdomainGenerator $generator, TokenUsageContext $usage): JsonResponse
    {
        $name = $usage->track(
            $request->user()->workspace()->id,
            fn (): string => $generator->generate(),
        );

        return response()->json(['name' => $name]);
    }
}
