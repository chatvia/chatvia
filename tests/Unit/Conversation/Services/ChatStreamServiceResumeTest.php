<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Approval\ResolveToolApprovalData;
use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\ToolApprovalDecision;
use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Support\CannedLlmProvider;
use Tests\Support\Conversation\NoToolSelector;
use Tests\Support\Conversation\ScriptedToolLoopRunner;
use Tests\Support\Conversation\TestChatContext;

/**
 * Resuming a turn paused for tool approval: the checkpoint is claimed
 * exactly once, so a second submit (or an expired checkpoint) gets an error
 * and never runs the tools; the turn continues under the paused answer's id
 * on the source the paused turn used.
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
    // No classifier call on the resumed answer.
    $this->agent->setSetting('enabled', false, 'guardrails');
    $this->conversation = Conversation::factory()->create([
        'chat_session_id' => Session::factory()->create(['agent_id' => $this->agent->id])->id,
        'source' => ConversationSource::HostedChat,
    ]);
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

    return iterator_to_array(app(ChatStreamService::class)->resume($checkpoint, $decisions), preserve_keys: false);
}

it('claims the checkpoint and continues the turn on the agent model under the paused answer id', function (): void {
    $checkpoint = resumeCheckpoint();

    $events = resumeEvents($checkpoint);

    expect(array_map(static fn (StreamEvent $e): EventType => $e->type, $events))->toBe([EventType::Start, EventType::Delta, EventType::Done])
        ->and($events[0]->data['assistant_message_id'])->toBe($checkpoint->assistant_message_id)
        ->and($events[0]->data['resumed'])->toBeTrue()
        ->and(end($events)->data['reason'])->toBe('complete')
        ->and($checkpoint->fresh()->status)->toBe(ToolApprovalStatus::Resolved)
        ->and($this->runner->appliedDecisions)->toBe([['call_1' => ToolApprovalDecision::Approve]])
        ->and($this->runner->runs)->toBe([['model' => $this->llm->internal_name, 'start_iteration' => 3]]);
});

it('refuses a second submit of the same approval without running the tools again', function (): void {
    $checkpoint = resumeCheckpoint();
    resumeEvents($checkpoint);

    $events = resumeEvents($checkpoint);
    $error = collect($events)->first(fn (StreamEvent $e): bool => $e->type === EventType::Error);

    expect($error->data['code'])->toBe(ErrorCode::Internal->value)
        ->and($error->data['message'])->toBe('This action was already handled or has expired.')
        ->and($error->id)->toBe($checkpoint->assistant_message_id)
        ->and($this->runner->appliedDecisions)->toHaveCount(1)
        ->and($this->runner->runs)->toHaveCount(1);
});

it('refuses an expired approval without running the tools', function (): void {
    $checkpoint = resumeCheckpoint();
    $checkpoint->forceFill(['expires_at' => now()->subMinute()])->save();

    $events = resumeEvents($checkpoint);

    expect(collect($events)->first(fn (StreamEvent $e): bool => $e->type === EventType::Error)->data['message'])
        ->toBe('This action was already handled or has expired.')
        ->and($this->runner->appliedDecisions)->toBe([])
        ->and($this->runner->runs)->toBe([]);
});

/**
 * The waiting period of an approval can outlive a gate's answer, so the
 * per-turn gates run again on resume: a workspace deactivated or a limit
 * reached while the turn waited refuses it, and the unclaimed checkpoint
 * stays resumable once the gate would allow it again.
 */
function resumeRefusalMessage(ToolApprovalCheckpoint $checkpoint): ?string
{
    $events = resumeEvents($checkpoint);

    return collect($events)->first(fn (StreamEvent $e): bool => $e->type === EventType::Error)?->data['message'];
}

it('refuses to resume for a deactivated workspace, without claiming the checkpoint', function (): void {
    $checkpoint = resumeCheckpoint();
    deactivateWorkspaceOf($this->agent);

    expect(resumeRefusalMessage($checkpoint))->toBe('Sorry — this assistant is unavailable right now. Please try again later.')
        ->and($checkpoint->fresh()->status)->toBe(ToolApprovalStatus::Pending)
        ->and($this->runner->runs)->toBe([]);
});

it('refuses to resume once the workspace reached its monthly message limit', function (): void {
    $checkpoint = resumeCheckpoint();
    MessageLimit::create([
        'workspace_id' => $this->agent->workspace_id,
        'monthly_limit' => 3,
        'messages_used' => 3,
        'period_starts_at' => now()->startOfMonth(),
    ]);

    $events = resumeEvents($checkpoint);
    $error = collect($events)->first(fn (StreamEvent $e): bool => $e->type === EventType::Error);

    expect($error->data['code'])->toBe(ErrorCode::MessageLimitReached->value)
        ->and($error->id)->toBe($checkpoint->assistant_message_id)
        ->and($checkpoint->fresh()->status)->toBe(ToolApprovalStatus::Pending)
        ->and($this->runner->runs)->toBe([]);
});

it('refuses to resume once the chatter reached their monthly message limit', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $checkpoint = resumeCheckpoint($chatter->id);
    MessageLimit::create([
        'chatter_id' => $chatter->id,
        'monthly_limit' => 3,
        'messages_used' => 3,
        'period_starts_at' => now()->startOfMonth(),
    ]);

    $events = resumeEvents($checkpoint);
    $error = collect($events)->first(fn (StreamEvent $e): bool => $e->type === EventType::Error);

    expect($error->data['code'])->toBe(ErrorCode::ChatterMessageLimitReached->value)
        ->and($checkpoint->fresh()->status)->toBe(ToolApprovalStatus::Pending)
        ->and($this->runner->runs)->toBe([]);
});

it('continues on the document model while the conversation has a PDF', function (): void {
    connectAiProviders(AiProvider::Mistral);
    Attachment::create([
        'workspace_id' => $this->conversation->workspace_id,
        'chat_conversation_id' => $this->conversation->id,
        'filepath' => 'workspaces/w/chat/terms.pdf',
        'filename' => 'terms.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 8,
    ]);

    resumeEvents(resumeCheckpoint());

    expect($this->runner->runs)->toBe([['model' => (string) config('chatvia.ai.models.document_chat'), 'start_iteration' => 3]]);
});
