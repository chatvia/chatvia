<?php

namespace App\Http\Controllers\Agent\Form;

use App\Http\Requests\Agent\Form\RewriteFormDescriptionRequest;
use Domain\Agent\Models\Agent;
use Domain\Form\Actions\RewriteFormDescriptionAction;
use Domain\Llm\Usage\TokenUsageContext;
use Illuminate\Http\JsonResponse;

/**
 * Rewrites a form's "when to show this form" instruction with the LLM, so the
 * owner can turn a rough note into a precise, intent-based trigger. The
 * suggestion comes back as JSON for the editor; nothing is saved until the
 * owner saves the form. Access is gated by the route middleware.
 */
class RewriteFormDescriptionController
{
    public function __invoke(RewriteFormDescriptionRequest $request, Agent $agent, RewriteFormDescriptionAction $rewrite, TokenUsageContext $usage): JsonResponse
    {
        $instructions = $usage->track(
            $agent->user_id,
            fn (): string => $rewrite->execute($request->toData()),
        );

        return response()->json(['agent_instructions' => $instructions]);
    }
}
