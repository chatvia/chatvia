<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Data\Turn\QaMatchData;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Services\Tooling\ToolCallAuditTrail;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\KnowledgeEnricher;
use Domain\Integration\Tool\Access\ToolAccessPolicy;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
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
 * A tool that needs a person's approval runs only through {@see runApproved()},
 * once the visitor has said yes out loud ({@see VoiceToolApprovals}); the
 * call's session holds it until then.
 */
final readonly class VoiceToolRunner
{
    /** What the model hears when a call is held for the visitor's yes. */
    public const string CONFIRM_FIRST_RESULT = 'Not done yet: this action needs the user\'s OK. In one short sentence, say what you are about to do, with the details that matter, and ask the user to answer yes or no. Do not say it is done. Only if they clearly say yes, call the same tool again with exactly the same arguments. If they say no, do not call it. If they change a detail, call it with the new details: they will be asked again.';

    /** A tool that started needing approval after the call checked it. */
    public const string NEEDS_APPROVAL_RESULT = 'This action needs the user\'s OK and was not run. Ask the user whether to go ahead.';

    /** The answer to a search with an empty query: small talk needs no knowledge. */
    public const string SMALL_TALK_RESULT = 'No search was needed. Reply to the user naturally and briefly.';

    /** Longest tool result read back to the model, in characters. */
    private const int MAX_RESULT_LENGTH = 6000;

    /** Longest tool name echoed back to the model or written to the audit. */
    private const int MAX_LOGGED_NAME_LENGTH = 100;

    public function __construct(
        private KnowledgeEnricher $knowledge,
        private ToolDispatcherInterface $dispatcher,
        private ToolDisplayResolver $display,
        private ToolAccessPolicy $access,
        private ToolApprovalPolicy $approvalPolicy,
        private ToolCallAuditTrail $audit,
    ) {}

    /**
     * Whether a tool the call offers needs the visitor's yes before it runs.
     *
     * @param  array<string, ToolEntry>  $tools  the call's tools, by name
     */
    public function requiresApproval(Agent $agent, array $tools, string $name): bool
    {
        return isset($tools[$name]) && $this->approvalPolicy->requiresApproval($agent, $tools[$name]->toolName());
    }

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

        $decision = $this->access->decide($agent, ToolName::parse($name));

        if ($decision === ToolAccessDecision::RequiresApproval) {
            return self::NEEDS_APPROVAL_RESULT;
        }

        $call = $this->pendingCall($agent, $callId, $name, $arguments, $decision);

        if ($decision === ToolAccessDecision::Deny) {
            $this->audit->record($context, $call, ToolCallOutcome::RateLimited, ToolDispatchResult::error(ToolLoopRunner::RATE_LIMITED_RESULT));

            return ToolLoopRunner::RATE_LIMITED_RESULT;
        }

        return $this->dispatch($turn, $call, ToolCallOutcome::Auto);
    }

    /**
     * Run a call the visitor said yes to. It is audited as approved by a
     * person, with their words (as stored, after the guardrails) as the
     * record of the yes. Like an approved typed call it is not rate limited:
     * the person in the loop is the limit.
     */
    public function runApproved(VoiceTurn $turn, string $callId, string $name, string $arguments): string
    {
        $call = $this->pendingCall($turn->context->requestData->agent, $callId, $name, $arguments, ToolAccessDecision::RequiresApproval);

        return $this->dispatch($turn, $call, ToolCallOutcome::Approved, $turn->words);
    }

    private function dispatch(VoiceTurn $turn, PendingToolCallData $call, ToolCallOutcome $outcome, ?string $decisionNote = null): string
    {
        $context = $turn->context;
        $startedAt = microtime(true);

        $result = $this->dispatcher->dispatch($context->requestData->agent, ToolCallData::named(
            $call->name,
            $call->arguments,
            $context->requestData->conversationId,
            $context->requestData->chatterExternalId,
        ));

        $context->toolCallNames[] = $call->name;
        $this->audit->record(
            $context,
            $call,
            $outcome,
            $result,
            (int) round((microtime(true) - $startedAt) * 1000),
            decidedByPerson: $outcome->wasGated(),
            decisionNote: $decisionNote,
        );

        return Str::limit($result->text, self::MAX_RESULT_LENGTH);
    }

    private function pendingCall(Agent $agent, string $callId, string $name, string $arguments, ToolAccessDecision $access): PendingToolCallData
    {
        $display = $this->display->resolve($agent, ToolName::parse($name));

        return new PendingToolCallData(
            toolCallId: $callId,
            name: $name,
            arguments: $arguments,
            requiresApproval: $access === ToolAccessDecision::RequiresApproval,
            access: $access,
            kind: $display->kind,
            label: $display->label,
            activityLabel: $display->activityLabel,
            isBuiltin: $display->isBuiltin,
            iconUrl: $display->iconUrl,
            brandColor: $display->brandColor,
        );
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
            return self::SMALL_TALK_RESULT;
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
