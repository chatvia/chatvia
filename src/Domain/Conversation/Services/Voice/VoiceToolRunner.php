<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Data\Turn\QaMatchData;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Services\Tooling\ToolCallAuditTrail;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\KnowledgeEnricher;
use Domain\Integration\Tool\Access\ToolAccessPolicy;
use Domain\Integration\Tool\Contracts\ToolDispatcherInterface;
use Domain\Integration\Tool\Data\ToolCallData;
use Domain\Integration\Tool\Data\ToolDispatchResult;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Data\ToolName;
use Domain\Integration\Tool\Enums\ToolAccessDecision;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Domain\Integration\Tool\Registry\ToolDisplayResolver;
use Illuminate\Support\Str;

/**
 * Runs a tool the model calls during a voice call, on the server, and
 * returns the text the model reads back. Knowledge search goes through the
 * typed turn's lookup (Q&A, search, relevance check, injection scan); every
 * other tool goes through the same governance as a typed turn — the access
 * decision, the rate limit, the dispatcher and the audit log.
 *
 * Only tools that needed no approval when the call started are offered. If
 * one needs approval by the time it is called, it is not run: approval is
 * a click, and a call has nothing to click.
 */
final readonly class VoiceToolRunner
{
    public const string NEEDS_APPROVAL_RESULT = 'This action needs a person\'s approval, which cannot be given during a voice call, so it was not run. Tell the user they can ask for it in the text chat.';

    public const string MISSING_QUERY_RESULT = 'Tool error: "query" is missing. Call search_knowledge again with the full question to look up.';

    /** Longest tool result read back to the model, in characters. */
    private const int MAX_RESULT_LENGTH = 6000;

    /** Longest tool name echoed back to the model or written to the audit. */
    private const int MAX_LOGGED_NAME_LENGTH = 100;

    public function __construct(
        private KnowledgeEnricher $knowledge,
        private ToolDispatcherInterface $dispatcher,
        private ToolDisplayResolver $display,
        private ToolAccessPolicy $access,
        private ToolCallAuditTrail $audit,
    ) {}

    /**
     * @param  array<string, ToolEntry>  $tools  the call's tools, by name
     */
    public function run(VoiceTurn $turn, array $tools, string $callId, string $name, string $arguments): string
    {
        if ($name === VoiceToolCatalog::KNOWLEDGE_TOOL) {
            return $this->searchKnowledge($turn, $arguments);
        }

        $context = $turn->context;
        $agent = $context->requestData->agent;

        if (! isset($tools[$name])) {
            $loggedName = mb_substr($name, 0, self::MAX_LOGGED_NAME_LENGTH);
            $message = sprintf(ToolLoopRunner::UNKNOWN_TOOL_RESULT, $loggedName);

            $this->audit->record($context, new PendingToolCallData(
                toolCallId: $callId,
                name: $loggedName,
                arguments: $arguments,
                requiresApproval: false,
                access: null,
                kind: ToolName::parse($loggedName)->kind,
                label: null,
            ), ToolCallOutcome::NotOffered, ToolDispatchResult::error($message));

            return $message;
        }

        $toolName = ToolName::parse($name);
        $display = $this->display->resolve($agent, $toolName);
        $decision = $this->access->decide($agent, $toolName);

        if ($decision === ToolAccessDecision::RequiresApproval) {
            return self::NEEDS_APPROVAL_RESULT;
        }

        $call = new PendingToolCallData(
            toolCallId: $callId,
            name: $name,
            arguments: $arguments,
            requiresApproval: false,
            access: $decision,
            kind: $display->kind,
            label: $display->label,
            activityLabel: $display->activityLabel,
            isBuiltin: $display->isBuiltin,
            iconUrl: $display->iconUrl,
            brandColor: $display->brandColor,
        );

        if ($decision === ToolAccessDecision::Deny) {
            $this->audit->record($context, $call, ToolCallOutcome::RateLimited, ToolDispatchResult::error(ToolLoopRunner::RATE_LIMITED_RESULT));

            return ToolLoopRunner::RATE_LIMITED_RESULT;
        }

        $startedAt = microtime(true);
        $result = $this->dispatcher->dispatch($agent, ToolCallData::named(
            $name,
            $arguments,
            $context->requestData->conversationId,
            $context->requestData->chatterExternalId,
        ));

        $context->toolCallNames[] = $name;
        $this->audit->record($context, $call, ToolCallOutcome::Auto, $result, (int) round((microtime(true) - $startedAt) * 1000));

        return Str::limit($result->text, self::MAX_RESULT_LENGTH);
    }

    /**
     * Look the question up and keep what was found on the turn, so the
     * reply is stored with its sources and confidence like a typed answer.
     */
    private function searchKnowledge(VoiceTurn $turn, string $arguments): string
    {
        $decoded = json_decode($arguments, true);
        $query = is_array($decoded) && is_string($decoded['query'] ?? null) ? trim($decoded['query']) : '';

        if (blank($query)) {
            return self::MISSING_QUERY_RESULT;
        }

        $context = $turn->context;
        $lookup = $this->knowledge->lookup($context, $query);

        if ($lookup->qaMatch instanceof QaMatchData) {
            $context->setQaMatch($lookup->qaMatch);
            $context->setConfidenceScore($lookup->qaMatch->confidenceScore);

            return prompt('voice/knowledge-result', ['query' => $query, 'answer' => $lookup->qaMatch->answer, 'items' => []]);
        }

        $sources = $context->sources
            ->merge($lookup->sources)
            ->unique(static fn (RetrievedSourceData $source): int => $source->id)
            ->sortByDesc(static fn (RetrievedSourceData $source): float => $source->relevance ?? 0.0)
            ->values();

        $context->setSources($sources);

        if (! $context->hasQaMatch()) {
            $context->setConfidenceScore($this->knowledge->confidence($sources));
        }

        return prompt('voice/knowledge-result', [
            'query' => $query,
            'answer' => null,
            'items' => $lookup->sources->values()->map(static fn (RetrievedSourceData $source, int $index): array => [
                'id' => $index + 1,
                // A chunk cannot close its block early.
                'content' => (string) preg_replace('/<(\/?source)\b/i', '&lt;$1', $source->content),
            ])->all(),
        ]);
    }
}
