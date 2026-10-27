<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Tooling;

use Domain\Conversation\Services\ChatContext;
use Domain\Guardrail\Services\GuardrailInspector;
use Domain\Guardrail\Services\GuardrailRecorder;

/**
 * The injection guard on a tool's result, before a model reads it. A result
 * comes from a system outside the app (a webhook, an MCP server) and may
 * carry instructions aimed at the model, so a flagged result is replaced by
 * a notice and the verdict is logged. Shared by typed turns
 * ({@see ToolLoopRunner}) and voice calls, so both read results the same way.
 */
class ToolResultGuard
{
    public function __construct(
        private readonly GuardrailInspector $guardrails,
        private readonly GuardrailRecorder $recorder,
    ) {}

    /**
     * The tool result the model may read: as returned, or a notice in its
     * place when it carries instructions aimed at the model. A turn that opts
     * out of guards (evals) gets the result as it is.
     */
    public function guarded(ChatContext $context, string $toolName, string $result): string
    {
        if (! $context->requestData->guardrailsEnabled) {
            return $result;
        }

        $agent = $context->requestData->agent;
        $scan = $this->guardrails->scanToolResult($agent, $toolName, $result);

        if (! $scan->triggered()) {
            return $result;
        }

        // The log keeps the result with its personal data masked.
        $this->recorder->record($context, $this->guardrails->redactPii($agent, $result), $scan);

        return $scan->text;
    }
}
