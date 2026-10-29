<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\CreateConversationAction;
use Domain\Conversation\Data\Conversations\CreateConversationData;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Str;

/**
 * A new hosted-chat conversation claims only the visitor's own pending
 * uploads, and at most the per-conversation cap of them: a script that
 * bypassed the composer could otherwise upload many orphans before the
 * conversation exists and claim them all here. An upload of another agent,
 * or one already bound to another conversation, is never claimed.
 */
it('binds no more pending uploads than the per-conversation cap', function (): void {
    config(['chatvia.agent.files.upload.max_files' => 3]);
    $agent = Agent::factory()->create();
    $orphanIds = Attachment::factory()->count(50)->pending($agent)->create()->pluck('external_id')->all();

    $conversationExternalId = app(CreateConversationAction::class)->execute(new CreateConversationData(
        scope: new ChatScopeData($agent, (string) Str::uuid7()),
        message: 'Hello',
        attachmentIds: $orphanIds,
    ));

    $conversation = Conversation::query()->firstWhere('external_id', $conversationExternalId);

    expect(Attachment::query()->where('chat_conversation_id', $conversation->id)->count())->toBe(3);
});

it('does not claim another agent\'s upload in the same workspace', function (): void {
    $agent = Agent::factory()->create();
    $sibling = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $orphan = Attachment::factory()->pending($sibling)->create();

    app(CreateConversationAction::class)->execute(new CreateConversationData(
        scope: new ChatScopeData($agent, (string) Str::uuid7()),
        message: 'Hello',
        attachmentIds: [$orphan->external_id],
    ));

    expect($orphan->fresh()->chat_conversation_id)->toBeNull();
});

it('does not re-bind an upload that already belongs to another conversation', function (): void {
    config(['chatvia.agent.files.upload.max_files' => 100]);
    $agent = Agent::factory()->create();
    $otherConversation = Conversation::factory()->forAgent($agent)->create();
    $alreadyBound = Attachment::factory()->forConversation($otherConversation)->create();

    app(CreateConversationAction::class)->execute(new CreateConversationData(
        scope: new ChatScopeData($agent, (string) Str::uuid7()),
        message: 'Hello',
        attachmentIds: [$alreadyBound->external_id],
    ));

    expect($alreadyBound->fresh()->chat_conversation_id)->toBe($otherConversation->id);
});
