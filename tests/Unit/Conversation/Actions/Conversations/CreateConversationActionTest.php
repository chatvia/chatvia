<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\CreateConversationAction;
use Domain\Conversation\Data\Conversations\CreateConversationData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;

/**
 * CreateConversationAction is the single entry point for materialising a
 * conversation from the hosted-chat surface. The attachment-cap behaviour
 * exercised below is the load-bearing security invariant flagged by the
 * architect review: a script that bypassed the composer could otherwise
 * upload N orphans pre-conversation and smuggle them all in here, defeating
 * MaxConversationAttachments on every subsequent turn AND racking up Mistral
 * OCR costs.
 */
function cccBuildOrphanAttachment(int $count, ?Agent $agent = null): array
{
    $agent ??= Agent::factory()->create();

    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id]);

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
    // Skip the LLM round-trip in QueryRewriter — irrelevant to the cap.
    config(['chatvia.ai.query.rewriter.enabled' => false]);

    [$agent, , $orphanIds] = cccBuildOrphanAttachment(50);

    // Stub the inner pipeline so we exercise only claimAttachments() — the
    // memory/turnstate/queryRewriter collaborators are tested elsewhere.
    $data = new CreateConversationData(
        agent: $agent,
        message: 'Hello',
        attachmentIds: $orphanIds,
        chatterUserId: null,
        ipAddress: '127.0.0.1',
        userAgent: 'Mozilla/5.0',
        referrerUrl: null,
        pageUrl: null,
        locale: 'en',
    );

    $conversationExternalId = app(CreateConversationAction::class)->execute($data);

    $conversation = Conversation::query()->where('external_id', $conversationExternalId)->first();
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
        agent: $agent,
        message: 'Hello',
        attachmentIds: [$alreadyBound->external_id],
        chatterUserId: null,
        ipAddress: '127.0.0.1',
        userAgent: 'Mozilla/5.0',
        referrerUrl: null,
        pageUrl: null,
        locale: 'en',
    );

    app(CreateConversationAction::class)->execute($data);

    expect($alreadyBound->fresh()->chat_conversation_id)->toBe($otherConversation->id);
});
