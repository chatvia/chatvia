<?php

namespace Domain\Conversation\Services\Tooling;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Tooling\EmittedToolCallData;
use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Enums\ToolApprovalDecision;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Sse\ToolCallPhase;
use Domain\Conversation\Services\Streaming\Transformers\StreamTransformer;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Turn\TurnStops;
use Domain\Integration\Tool\Access\ToolAccessPolicy;
use Domain\Integration\Tool\Contracts\ToolDispatcherInterface;
use Domain\Integration\Tool\Data\ToolDispatchResult;
use Domain\Integration\Tool\Data\ToolName;
use Domain\Integration\Tool\Enums\ToolAccessDecision;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Domain\Integration\Tool\Registry\ToolDisplayResolver;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Services\AiCallTags;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * The provider-agnostic agentic loop. Streams an LLM completion, drains any
 * emitted tool calls, runs each through the governance gate (approval / rate
 * limit), dispatches the allowed ones, and iterates until the model produces a
 * final answer. After the last allowed round (the admin's "Tool rounds per
 * answer") the model answers once more without tools, so a turn never ends
 * on tool results alone.
 *
 * Every tool-call decision — auto, approved, denied, rate limited or not
 * offered — is announced for the governance audit ({@see ToolCallAuditTrail},
 * {@see ToolCallCompleted}) off the hot path. A tool's result passes the
 * injection guard before the model reads it ({@see ToolResultGuard}).
 *
 * Shared by every source that needs the loop — `LlmSource` runs it on the
 * agent's configured provider, `AttachmentSource` on the model for chat
 * files with the files added to the user turn, and evals in capture-only mode. Each caller owns
 * its own start/finish envelope; the runner only handles the iterations and
 * the tool plumbing. When a turn pauses for approval its calls are stored as
 * {@see PendingToolCallData} on a ToolApprovalCheckpoint.
 */
class ToolLoopRunner
{
    public const string APPROVAL_REASON = 'This action can make changes, so it needs your approval before it runs.';

    public const string DENIED_RESULT = 'The user reviewed this action and chose not to approve it. Acknowledge their decision briefly and continue helping without running it; do not call this tool again unless they ask.';

    public const string RATE_LIMITED_RESULT = 'This tool is temporarily rate-limited for this agent and was not run. Do not retry it now; continue helping the user without it.';

    public const string SIGN_IN_REQUIRED_RESULT = 'This action needs the approval of a signed-in user, so it was not run. Tell the user you cannot do this in this chat, and continue helping without it.';

    public const string UNKNOWN_TOOL_RESULT = 'Tool error: "%s" is not one of the tools available to you right now, so it was not run. Use only the tools you were given, or answer without a tool.';

    /** Longest tool name echoed back to the model or written to the log. */
    private const int MAX_LOGGED_NAME_LENGTH = 100;

    public function __construct(
        private readonly StreamTransformer $transformer,
        private readonly ToolDispatcherInterface $dispatcher,
        private readonly ToolDisplayResolver $displayResolver,
        private readonly ToolAccessPolicy $accessPolicy,
        private readonly ToolApprovalStore $approvalStore,
        private readonly ToolCallAuditTrail $auditTrail,
        private readonly ToolResultGuard $resultGuard,
        private readonly AiCallTags $tags,
        private readonly ToolCallFactory $calls,
        private readonly TurnStops $stops = new TurnStops,
    ) {}

    /**
     * Run the tool loop. Returns true when paused for approval (the checkpoint
     * and the `awaiting_approval` / `done` events were already emitted) and
     * false when a final answer was streamed.
     *
     * When `$captureToolCallsOnly` is true (evals), the loop never dispatches a
     * tool: the first turn that emits tool calls records them on
     * {@see ChatContext::$capturedToolCalls} and stops. This lets an eval grade
     * the agent's INTENDED call without the cost or side effects of running it.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages  OpenAI-shaped history, extended in place
     * @param  array<int, array<string, mixed>>  $toolDefinitions  OpenAI-shaped tools offered this turn
     * @param  array<string, mixed>  $extraSettings  per-turn settings (temperature, etc.)
     * @return Generator<int, StreamEvent, mixed, bool>
     */
    public function run(
        ChatContext $context,
        LlmProviderInterface $provider,
        string $modelName,
        array &$llmMessages,
        array $toolDefinitions,
        array $extraSettings = [],
        int $startIteration = 0,
        bool $captureToolCallsOnly = false,
    ): Generator {
        $maxIterations = max(1, (int) config('chatvia.agent.tools.max_iterations'));

        // The model may only call what it was offered on this turn. The names
        // gate both the transformer (no JSON sniffing without tools) and the
        // dispatch below (an invented or stale name never reaches a backend,
        // so it cannot skip the surface filter, routing or approval).
        $offeredNames = $this->offeredToolNames($toolDefinitions);
        $settings = $this->completionSettings($extraSettings, $toolDefinitions);

        for ($iteration = $startIteration; $iteration < $maxIterations; $iteration++) {
            // A stopped turn asks the model nothing more.
            $this->stops->throwIfStopped($context);
            $context->offeredToolNames = $offeredNames;

            yield from $this->streamCompletion($context, $provider, $modelName, $llmMessages, $settings);

            $toolCalls = $context->takeToolCalls();

            if ($toolCalls === []) {
                return false;
            }

            // Eval mode: there is no tool result to feed back, so the loop
            // cannot continue — and that's the point: an eval grades the
            // decision, not the (expensive, side-effecting) execution.
            if ($captureToolCallsOnly) {
                $this->captureToolCalls($context, $toolCalls);

                return false;
            }

            // What the model wrote before these calls says what it is about
            // to do: a step of the chain, not part of the saved answer.
            $context->addTextStep($context->streamedMessage);

            $paused = yield from $this->resolveToolCalls($context, $llmMessages, $toolCalls, $offeredNames, $iteration);

            if ($paused) {
                return true;
            }

            // Drop the visible text between iterations so the saved answer is
            // only the post-tool reply.
            $context->resetStreamedMessage();
        }

        // Out of rounds while the model still wanted tools: without one more
        // completion the turn would end on tool results and an empty reply.
        yield from $this->finalAnswer($context, $provider, $modelName, $llmMessages, $settings, $offeredNames);

        return false;
    }

    /**
     * Apply the person's decisions to a resumed turn's calls, appending a
     * `role: 'tool'` result for each so the loop can continue.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, PendingToolCallData>  $pendingCalls
     * @param  array<string, ToolApprovalDecision>  $decisions  keyed by tool_call_id
     * @return Generator<int, StreamEvent>
     */
    public function applyPendingCalls(ChatContext $context, array &$llmMessages, array $pendingCalls, array $decisions): Generator
    {
        foreach ($pendingCalls as $call) {
            // Rate-limited at decision time — blocked whatever the person chose.
            if ($call->isRateLimited()) {
                yield from $this->refuseCall($context, $llmMessages, $call, ToolCallOutcome::RateLimited, self::RATE_LIMITED_RESULT);

                continue;
            }

            // Only a signed-in person (a chatter or staff) may approve: an
            // anonymous visitor's yes would run the call with the workspace's
            // own connector credentials.
            $approved = ($decisions[$call->toolCallId] ?? null) === ToolApprovalDecision::Approve
                && self::personCanApprove($context);

            if ($call->requiresApproval && ! $approved) {
                $this->appendToolResult($llmMessages, $call->toolCallId, self::DENIED_RESULT);
                $this->auditTrail->record($context, $call, ToolCallOutcome::Denied, ToolDispatchResult::text(self::DENIED_RESULT), decidedByPerson: true);

                continue;
            }

            // Never gated, or approved: run it for real.
            $outcome = $call->requiresApproval ? ToolCallOutcome::Approved : ToolCallOutcome::Auto;

            yield from $this->dispatchToolCall($context, $llmMessages, $call, $outcome, decidedByPerson: $call->requiresApproval);
        }
    }

    /**
     * Whether the person on this turn may approve a gated call: a signed-in
     * chatter or staff account, never an anonymous visitor. Tools that need
     * approval are not offered to anyone else ({@see ToolSelector::selectForVisitorTurn()}).
     */
    public static function personCanApprove(ChatContext $context): bool
    {
        return $context->requestData->chatterId !== null || $context->requestData->actorUserId !== null;
    }

    /**
     * Resolve one iteration's tool calls. Returns true when the turn paused
     * for approval.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, EmittedToolCallData>  $toolCalls
     * @param  array<int, string>  $offeredNames
     * @return Generator<int, StreamEvent, mixed, bool>
     */
    private function resolveToolCalls(ChatContext $context, array &$llmMessages, array $toolCalls, array $offeredNames, int $iteration): Generator
    {
        // The assistant message that requested the tools goes first, so the
        // snapshot and the next iteration carry it.
        $llmMessages[] = $this->assistantToolCallsMessage($context, $toolCalls);

        $calls = $this->governedCalls(
            $context->requestData->agent,
            $this->rejectUnofferedCalls($context, $llmMessages, $toolCalls, $offeredNames),
        );

        // Nobody signed in can approve: a gated call is refused at once rather
        // than paused for a visitor's click. The selector does not offer such
        // tools to a visitor, so this only catches a policy that changed
        // mid-turn.
        if (! self::personCanApprove($context)) {
            $calls = yield from $this->refuseGatedCalls($context, $llmMessages, $calls);
        }

        // OpenAI needs a result for every tool_call_id before the next
        // assistant turn, so one gated call pauses the whole turn. Refused
        // (rate-limited) calls in the same turn are resolved on resume.
        if (array_any($calls, static fn (PendingToolCallData $call): bool => $call->requiresApproval)) {
            yield from $this->pauseForApproval($context, $llmMessages, $calls, $iteration);

            return true;
        }

        foreach ($calls as $call) {
            yield from $call->isRateLimited()
                ? $this->refuseCall($context, $llmMessages, $call, ToolCallOutcome::RateLimited, self::RATE_LIMITED_RESULT)
                : $this->dispatchToolCall($context, $llmMessages, $call);
        }

        return false;
    }

    /**
     * Refuse every gated call (see {@see personCanApprove()}) and return the
     * rest.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, PendingToolCallData>  $calls
     * @return Generator<int, StreamEvent, mixed, array<int, PendingToolCallData>>
     */
    private function refuseGatedCalls(ChatContext $context, array &$llmMessages, array $calls): Generator
    {
        $kept = [];

        foreach ($calls as $call) {
            if ($call->requiresApproval) {
                yield from $this->refuseCall($context, $llmMessages, $call, ToolCallOutcome::NotOffered, self::SIGN_IN_REQUIRED_RESULT);
            } else {
                $kept[] = $call;
            }
        }

        return $kept;
    }

    /**
     * The settings sent with every completion of the loop: the per-turn extras
     * plus the offered tools, when there are any.
     *
     * @param  array<string, mixed>  $extraSettings
     * @param  array<int, array<string, mixed>>  $toolDefinitions
     * @return array<string, mixed>
     */
    private function completionSettings(array $extraSettings, array $toolDefinitions): array
    {
        if ($toolDefinitions === []) {
            return $extraSettings;
        }

        return [...$extraSettings, 'tools' => $toolDefinitions, 'tool_choice' => 'auto'];
    }

    /**
     * The completion after the last round: the tools stay in the request (the
     * history refers to them) but the model may not call one, so it answers
     * from the results it has. A provider that ignores `tool_choice` may still
     * ask for tools; those calls are dropped, never run. The offered names
     * stay set so a call the model writes as text is caught, not shown.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<string, mixed>  $settings
     * @param  array<int, string>  $offeredNames
     * @return Generator<int, StreamEvent>
     */
    private function finalAnswer(ChatContext $context, LlmProviderInterface $provider, string $modelName, array $llmMessages, array $settings, array $offeredNames): Generator
    {
        $context->offeredToolNames = $offeredNames;

        if (isset($settings['tools'])) {
            $settings['tool_choice'] = 'none';
        }

        yield from $this->streamCompletion($context, $provider, $modelName, $llmMessages, $settings);

        $context->takeToolCalls();
    }

    /**
     * Stream one completion through the transformer. Only the call that
     * sends it is tagged: Spectra reads the tags then, not while the stream
     * is read.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<string, mixed>  $settings
     * @return Generator<int, StreamEvent>
     */
    private function streamCompletion(ChatContext $context, LlmProviderInterface $provider, string $modelName, array $llmMessages, array $settings): Generator
    {
        $response = $this->tags->during(AiCallTag::ChatReply, fn (): ResponseInterface => $provider->stream($modelName, $llmMessages, $settings));

        yield from $this->transformer->transform($response, $context);
    }

    /**
     * @param  array<int, EmittedToolCallData>  $toolCalls
     */
    private function captureToolCalls(ChatContext $context, array $toolCalls): void
    {
        foreach ($toolCalls as $call) {
            $context->capturedToolCalls[] = ['name' => $call->name, 'arguments' => $call->arguments];
            $context->toolCallNames[] = $call->name;
        }
    }

    /**
     * The assistant message that requested the tools, in the OpenAI shape.
     *
     * @param  array<int, EmittedToolCallData>  $toolCalls
     * @return array<string, mixed>
     */
    private function assistantToolCallsMessage(ChatContext $context, array $toolCalls): array
    {
        return [
            'role' => 'assistant',
            'content' => $context->streamedMessage,
            'tool_calls' => array_map(static fn (EmittedToolCallData $call): array => $call->toOpenAiToolCall(), $toolCalls),
        ];
    }

    /**
     * Refuse calls to tools that were not offered: answer each with a tool
     * error right away (the model needs a result for every tool_call_id) and
     * keep it away from approval and dispatch. Nothing is dispatched and no
     * event is streamed (there is no real tool to show), but the refusal is
     * audited as `not_offered` so the workspace sees what the model tried.
     * Returns the offered calls.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, EmittedToolCallData>  $toolCalls
     * @param  array<int, string>  $offeredNames
     * @return array<int, EmittedToolCallData>
     */
    private function rejectUnofferedCalls(ChatContext $context, array &$llmMessages, array $toolCalls, array $offeredNames): array
    {
        $offeredCalls = [];

        foreach ($toolCalls as $call) {
            if (in_array($call->name, $offeredNames, true)) {
                $offeredCalls[] = $call;

                continue;
            }

            $loggedName = Str::substr($call->name, 0, self::MAX_LOGGED_NAME_LENGTH);
            $message = sprintf(self::UNKNOWN_TOOL_RESULT, $loggedName);

            $this->appendToolResult($llmMessages, $call->id, $message);

            Log::warning('Refused a tool call to a tool that was not offered', [
                'agent_id' => $context->requestData->agent->id,
                'tool' => $loggedName,
            ]);

            $this->auditTrail->record($context, $this->unofferedCall($call, $loggedName), ToolCallOutcome::NotOffered, ToolDispatchResult::error($message));
        }

        return $offeredCalls;
    }

    /**
     * An unoffered call in the shape the audit records. The name is the
     * shortened one, since the model may invent any text; it has no display
     * details because no such tool exists.
     */
    private function unofferedCall(EmittedToolCallData $call, string $name): PendingToolCallData
    {
        return new PendingToolCallData(
            toolCallId: $call->id,
            name: $name,
            arguments: $call->arguments,
            requiresApproval: false,
            access: null,
            kind: ToolName::parse($name)->kind,
            label: null,
        );
    }

    /**
     * Each call with the governance decision (run, ask a person, refuse) and
     * the display details the chat UI shows for it.
     *
     * @param  array<int, EmittedToolCallData>  $toolCalls
     * @return array<int, PendingToolCallData>
     */
    private function governedCalls(Agent $agent, array $toolCalls): array
    {
        return array_map(function (EmittedToolCallData $call) use ($agent): PendingToolCallData {
            $name = ToolName::parse($call->name);
            $display = $this->displayResolver->resolve($agent, $name);
            $decision = $this->accessPolicy->decide($agent, $name);
            $requiresApproval = $decision === ToolAccessDecision::RequiresApproval;

            return new PendingToolCallData(
                toolCallId: $call->id,
                name: $call->name,
                arguments: $call->arguments,
                requiresApproval: $requiresApproval,
                access: $decision,
                kind: $display->kind,
                label: $display->label,
                activityLabel: $display->activityLabel,
                isBuiltin: $display->isBuiltin,
                iconUrl: $display->iconUrl,
                brandColor: $display->brandColor,
                reason: $requiresApproval ? self::APPROVAL_REASON : null,
            );
        }, $toolCalls);
    }

    /**
     * Checkpoint the turn, show an approval card for every gated call, and end
     * the stream until the person decides.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, PendingToolCallData>  $calls
     * @return Generator<int, StreamEvent>
     */
    private function pauseForApproval(ChatContext $context, array $llmMessages, array $calls, int $iteration): Generator
    {
        $checkpoint = $this->approvalStore->persist($context, $llmMessages, $calls, $iteration);

        foreach ($calls as $call) {
            if (! $call->requiresApproval) {
                continue;
            }

            yield StreamEvent::tool(
                assistantMessageId: $context->assistantMessageId,
                phase: ToolCallPhase::AwaitingApproval,
                toolCallId: $call->toolCallId,
                name: $call->name,
                args: $call->arguments,
                kind: $call->kind->value,
                label: $call->label,
                activityLabel: $call->activityLabel,
                iconUrl: $call->iconUrl,
                brandColor: $call->brandColor,
                approvalId: $checkpoint->external_id,
                reason: $call->reason,
            );
        }

        yield StreamEvent::done($context->assistantMessageId, 'awaiting_approval');
    }

    /**
     * Dispatch one tool call: emit `start`/`end` events, append the
     * `role: 'tool'` result, and record the governance audit.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  bool  $decidedByPerson  true when a person resolved a gated call (sets decided_at)
     * @return Generator<int, StreamEvent>
     */
    private function dispatchToolCall(
        ChatContext $context,
        array &$llmMessages,
        PendingToolCallData $call,
        ToolCallOutcome $outcome = ToolCallOutcome::Auto,
        bool $decidedByPerson = false,
    ): Generator {
        // No tool starts once the visitor stopped the turn.
        $this->stops->throwIfStopped($context);

        $assistantId = $context->assistantMessageId;

        // Built-in capabilities are plumbing the chat never shows as a step.
        if (! $call->isBuiltin) {
            $context->addToolStep($call);
        }

        yield StreamEvent::tool(
            assistantMessageId: $assistantId,
            phase: ToolCallPhase::Start,
            toolCallId: $call->toolCallId,
            name: $call->name,
            args: $call->arguments,
            kind: $call->kind->value,
            label: $call->label,
            activityLabel: $call->activityLabel,
            iconUrl: $call->iconUrl,
            brandColor: $call->brandColor,
            internal: $call->isBuiltin,
        );

        $startedAt = microtime(true);
        $result = $this->dispatcher->dispatch(
            $context->requestData->agent,
            $this->calls->make($context->requestData, $call->name, $call->arguments),
        );
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $context->toolCallNames[] = $call->name;
        $resultText = $this->resultGuard->guarded($context, $call->name, $result->text);
        $this->appendToolResult($llmMessages, $call->toolCallId, $resultText);

        if ($result->artifacts !== []) {
            $context->artifacts = [...$context->artifacts, ...$result->artifacts];
        }

        // The result text is for the model only: it can hold records from a
        // connected system and never passes the output guardrail, so the
        // client gets the status and any artifacts, never the text.
        yield StreamEvent::tool(
            assistantMessageId: $assistantId,
            phase: ToolCallPhase::End,
            toolCallId: $call->toolCallId,
            name: $call->name,
            artifacts: $result->artifacts !== [] ? $result->artifacts : null,
        );

        $this->auditTrail->record($context, $call, $outcome, $result, $latencyMs, $decidedByPerson);
    }

    /**
     * Resolve a blocked call without dispatching it: surface an `end` event,
     * append a recoverable tool result so the model continues, and record the
     * governance audit.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @return Generator<int, StreamEvent>
     */
    private function refuseCall(ChatContext $context, array &$llmMessages, PendingToolCallData $call, ToolCallOutcome $outcome, string $message): Generator
    {
        yield StreamEvent::tool(
            assistantMessageId: $context->assistantMessageId,
            phase: ToolCallPhase::End,
            toolCallId: $call->toolCallId,
            name: $call->name,
        );

        $this->appendToolResult($llmMessages, $call->toolCallId, $message);

        $this->auditTrail->record($context, $call, $outcome, ToolDispatchResult::text($message));
    }

    /**
     * @param  array<int, array<string, mixed>>  $llmMessages
     */
    private function appendToolResult(array &$llmMessages, string $toolCallId, string $content): void
    {
        $llmMessages[] = [
            'role' => 'tool',
            'tool_call_id' => $toolCallId,
            'content' => $content,
        ];
    }

    /**
     * The function names in an OpenAI-shaped `tools` list.
     *
     * @param  array<int, array<string, mixed>>  $toolDefinitions
     * @return array<int, string>
     */
    private function offeredToolNames(array $toolDefinitions): array
    {
        $names = [];

        foreach ($toolDefinitions as $definition) {
            $name = $definition['function']['name'] ?? null;

            if (filled($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
