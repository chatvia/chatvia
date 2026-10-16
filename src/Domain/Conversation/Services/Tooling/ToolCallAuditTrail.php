<?php

namespace Domain\Conversation\Services\Tooling;

use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Services\ChatContext;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Tool\Data\ToolDispatchResult;
use Domain\Integration\Tool\Data\ToolName;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Domain\Integration\Tool\Enums\ToolKind;

/**
 * Announces every resolved tool call for the governance audit. A queued
 * listener writes the immutable `agent_activities` row, so this never touches
 * the database on the streaming hot path. Agent, user and connector names are
 * copied in so the record survives their later deletion. First-party builtin
 * capabilities (charts, forms, leads) run directly and are not audited.
 */
class ToolCallAuditTrail
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function record(
        ChatContext $context,
        PendingToolCallData $call,
        ToolCallOutcome $outcome,
        ToolDispatchResult $result,
        ?int $latencyMs = null,
        bool $decidedByPerson = false,
        ?string $decisionNote = null,
    ): void {
        $request = $context->requestData;
        $agent = $request->agent;
        $isMcp = $call->kind === ToolKind::Mcp;

        // No database lookup: the tool name says which MCP server it is on,
        // and the display label already holds the connector's name.
        $name = ToolName::parse($call->name);
        $connectorSlug = $isMcp ? $name->serverSlug : null;

        if ($connectorSlug !== null && $this->catalog->isBuiltinSlug($connectorSlug)) {
            return;
        }

        ToolCallCompleted::dispatch(
            arguments: $call->arguments,
            agentId: (int) $agent->id,
            agentName: (string) $agent->name,
            workspaceId: (int) $agent->workspace_id,
            userId: $request->actorUserId,
            userName: $request->actorName,
            chatterId: $request->chatterId,
            conversationExternalId: $request->conversationId,
            traceId: $context->assistantMessageId,
            requestId: $request->requestId,
            toolCallId: $call->toolCallId,
            toolName: $isMcp ? $name->tool : $name->value,
            toolKind: $call->kind->value,
            connectorSlug: $connectorSlug,
            connectorName: $isMcp ? $call->label : null,
            resultText: $result->text,
            isError: $result->isError,
            latencyMs: $latencyMs,
            gated: $outcome->wasGated(),
            approvalOutcome: $outcome->value,
            decidedAt: $decidedByPerson ? now()->toIso8601String() : null,
            decisionNote: $decisionNote,
        );
    }
}
