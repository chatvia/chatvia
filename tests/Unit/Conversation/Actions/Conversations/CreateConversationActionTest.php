<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\CreateConversationAction;
use Domain\Conversation\Data\Conversations\CreateConversationData;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Jobs\StoreMessageJob;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * CreateConversationAction is the single entry point for materialising a
 * conversation from the hosted-chat surface. The attachment cap matters for
 * security: a script that bypassed the composer could otherwise upload many
 * orphans before the conversation exists and claim them all here, defeating
 * MaxConversationAttachments on every later turn and running up document
 * reading costs.
 */
function cccBuildOrphanAttachment(int $count, ?Agent $agent = null): array
{
    $agent ??= Agent::factory()->create();

    $session = Session::factory()->create(['agent_id' => $agent->id]);
    Conversation::factory()->create(['chat_session_id' => $session->id]);

    // Create the rows with NO conversation binding so they remain orphans
    // that claimAttachments() can claim.
    // external_id is auto-generated on create (HasExternalId) and is not
    // fillable, so we read it back rather than passing it in.
    $ids = [];
    for ($i = 0; $i < $count; $i++) {
        $att = Attachment::create([
            'chat_conversation_id' => null,
            'chat_message_id' => null,
            'filepath' => 'users/test/agents/test/attachments/orphan-'.$i,
            'filename' => "file-{$i}.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 8,
        ]);
        $ids[] = $att->external_id;
    }

    return [$agent, $session, $ids];
}

it('caps the number of attachments bound to a new conversation at config(chatvia.agent.files.upload.max_files)', function (): void {
    config(['chatvia.agent.files.upload.max_files' => 3]);

    [$agent, , $orphanIds] = cccBuildOrphanAttachment(50);

    // Only the claim is exercised here; the other collaborators are tested
    // elsewhere.
    $data = new CreateConversationData(
        scope: new ChatScopeData($agent, (string) Str::uuid()),
        message: 'Hello',
        ipAddress: '127.0.0.1',
        userAgent: 'Mozilla/5.0',
        locale: 'en',
        attachmentIds: $orphanIds,
    );

    $conversationExternalId = app(CreateConversationAction::class)->execute($data);

    $conversation = Conversation::query()->firstWhere('external_id', $conversationExternalId);
    $boundCount = Attachment::query()
        ->where('chat_conversation_id', $conversation->id)
        ->count();

    expect($boundCount)->toBe(3);
});

it('does NOT re-bind attachments that already belong to a different conversation', function (): void {
    config(['chatvia.agent.files.upload.max_files' => 100]);

    [$agent] = cccBuildOrphanAttachment(0);

    $otherSession = Session::factory()->create(['agent_id' => $agent->id]);
    $otherConversation = Conversation::factory()->create(['chat_session_id' => $otherSession->id]);

    // Attachment is bound to another conversation already.
    $alreadyBound = Attachment::create([
        'chat_conversation_id' => $otherConversation->id,
        'filepath' => 'users/test/agents/test/attachments/already-bound',
        'filename' => 'bound.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 8,
    ]);

    $data = new CreateConversationData(
        scope: new ChatScopeData($agent, (string) Str::uuid()),
        message: 'Hello',
        ipAddress: '127.0.0.1',
        userAgent: 'Mozilla/5.0',
        locale: 'en',
        attachmentIds: [$alreadyBound->external_id],
    );

    app(CreateConversationAction::class)->execute($data);

    expect($alreadyBound->fresh()->chat_conversation_id)->toBe($otherConversation->id);
});

it('records the first message masked and starts a hosted-chat conversation in the visitor session', function (): void {
    Bus::fake();

    $agent = Agent::factory()->create();
    $sessionId = (string) Str::uuid();

    $conversationId = app(CreateConversationAction::class)->execute(new CreateConversationData(
        scope: new ChatScopeData($agent, $sessionId),
        message: 'Where is my order? Mail me at dana@example.com',
    ));

    $conversation = Conversation::query()->firstWhere('external_id', $conversationId);
    $history = app(ChatMemory::class)
        ->withScope(new ChatScopeData($agent, $sessionId, $conversationId))
        ->getMessagesForConversation();

    expect($conversation->source)->toBe(ConversationSource::HostedChat)
        ->and($conversation->session->external_id)->toBe($sessionId)
        ->and($history)->toHaveCount(1)
        ->and($history[0]['content'])->toBe('Where is my order? Mail me at [EMAIL_1]');

    Bus::assertDispatched(StoreMessageJob::class, fn (StoreMessageJob $job): bool => $job->payload instanceof UserTurnData
        && $job->payload->message === 'Where is my order? Mail me at [EMAIL_1]'
        && $job->payload->message_for_llm === 'Where is my order? Mail me at [EMAIL_1]');
});
