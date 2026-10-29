<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Services\MessageAllowance;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Approval\ResolveToolApprovalData;
use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Enums\ToolApprovalDecision;
use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatTurnStreamer;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Support\Conversation\NoToolSelector;
use Tests\Support\Conversation\ScriptedToolLoopRunner;
use Tests\Support\Conversation\TestChatContext;
use Tests\Support\Llm\CannedLlmProvider;

/**
 * Resuming a turn paused for tool approval: the checkpoint is claimed
 * exactly once, so a second submit (or an expired checkpoint) gets an error
 * and never runs the tools; the turn continues under the paused answer's id
 * on the source the paused turn used. The waiting period can outlive a
 * gate's answer, so the per-turn gates run again on resume: a workspace
 * deactivated or a monthly limit reached while the turn waited refuses it,
 * and the unclaimed checkpoint stays pending.
 */
beforeEach(function (): void {
    Bus::fake();
    Event::fake([AssistantReplyCompleted::class]);
    bindFakeLlmProvider(new CannedLlmProvider);
    app()->instance(ToolSelector::class, new NoToolSelector);

    $this->runner = ScriptedToolLoopRunner::answering('The ticket is closed.');
    app()->instance(ToolLoopRunner::class, $this->runner);

    $this->llm = Llm::factory()->create();
    $this->agent = Agent::factory()->create(['llm_id' => $this->llm->id]);
    $this->agent->setSetting('enabled', false, 'guardrails');
    $this->conversation = Conversation::factory()->forAgent($this->agent)->hostedChat()->create();
});

/** The turn of this test's conversation, paused on one gated call in its third round. */
function resumeCheckpoint(?int $chatterId = null): ToolApprovalCheckpoint
{
    $context = TestChatContext::make(
        agent: test()->agent,
        message: 'Close ticket 12',
        llm: test()->llm,
        externalSessionId: test()->conversation->session->external_id,
        conversationId: test()->conversation->external_id,
        chatterId: $chatterId,
    );
    $pending = PendingToolCallData::fromArray([
        'tool_call_id' => 'call_1',
        'name' => 'close_ticket',
        'arguments' => '{"id":12}',
        'requires_approval' => true,
        'kind' => 'function',
        'label' => 'Close ticket',
    ]);

    return app(ToolApprovalStore::class)->persist($context, $context->llmMessages, [$pending], iteration: 2);
}

/** @return list<StreamEvent> */
function resumeEvents(ToolApprovalCheckpoint $checkpoint): array
{
    $decisions = new ResolveToolApprovalData(['call_1' => ToolApprovalDecision::Approve]);

    return iterator_to_array(app(ChatTurnStreamer::class)->resume($checkpoint, $decisions), preserve_keys: false);
}

function resumeError(ToolApprovalCheckpoint $checkpoint): ?StreamEvent
{
    return collect(resumeEvents($checkpoint))->first(fn (StreamEvent $e): bool => $e->type === EventType::Error);
}

it('claims the checkpoint once and continues the turn under the paused answer id on the paused turn\'s model', function (): void {
    $checkpoint = resumeCheckpoint();

    $events = resumeEvents($checkpoint);
    $second = resumeError($checkpoint);
    $fileModel = useChatFiles();
    Attachment::factory()->forConversation($this->conversation)->create();
    resumeEvents(resumeCheckpoint());

    expect(array_map(static fn (StreamEvent $e): EventType => $e->type, $events))->toBe([EventType::Start, EventType::Delta, EventType::Done])
        ->and($events[0]->data['assistant_message_id'])->toBe($checkpoint->assistant_message_id)
        ->and($events[0]->data['resumed'])->toBeTrue()
        ->and(end($events)->data['reason'])->toBe('complete')
        ->and($checkpoint->fresh()->status)->toBe(ToolApprovalStatus::Resolved)
        ->and($second->data['code'])->toBe(ErrorCode::Internal->value)
        ->and($second->data['message'])->toBe('This action was already handled or has expired.')
        ->and($second->id)->toBe($checkpoint->assistant_message_id)
        ->and($this->runner->appliedDecisions)->toHaveCount(2)
        ->and($this->runner->appliedDecisions[0])->toBe(['call_1' => ToolApprovalDecision::Approve])
        ->and($this->runner->runs)->toBe([
            ['model' => $this->llm->name, 'start_iteration' => 3],
            ['model' => $fileModel->name, 'start_iteration' => 3],
        ]);
});

it('refuses an expired approval, and one of a workspace deactivated meanwhile, without running the tools', function (): void {
    $expired = resumeCheckpoint();
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();
    $expiredError = resumeError($expired);
    $waiting = resumeCheckpoint();
    deactivateWorkspaceOf($this->agent);

    $deactivatedError = resumeError($waiting);

    expect($expiredError->data['message'])->toBe('This action was already handled or has expired.')
        ->and($deactivatedError->data['message'])->toBe('Sorry — this assistant is unavailable right now. Please try again later.')
        ->and($waiting->fresh()->status)->toBe(ToolApprovalStatus::Pending)
        ->and($this->runner->appliedDecisions)->toBe([])
        ->and($this->runner->runs)->toBe([]);
});

it('refuses to resume once the workspace or the chatter reached the monthly message limit, without claiming the checkpoint', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $chatterCheckpoint = resumeCheckpoint($chatter->id);
    MessageLimit::factory()->forChatter($chatter)->reached()->create();
    $chatterError = resumeError($chatterCheckpoint);
    $workspaceCheckpoint = resumeCheckpoint();
    app(MessageAllowance::class)->forgetLimit(MessageLimit::factory()->reached()->create(['workspace_id' => $this->agent->workspace_id]));

    $workspaceError = resumeError($workspaceCheckpoint);

    expect($chatterError->data['code'])->toBe(ErrorCode::ChatterMessageLimitReached->value)
        ->and($workspaceError->data['code'])->toBe(ErrorCode::MessageLimitReached->value)
        ->and($workspaceError->id)->toBe($workspaceCheckpoint->assistant_message_id)
        ->and($chatterCheckpoint->fresh()->status)->toBe(ToolApprovalStatus::Pending)
        ->and($workspaceCheckpoint->fresh()->status)->toBe(ToolApprovalStatus::Pending)
        ->and($this->runner->runs)->toBe([]);
});
