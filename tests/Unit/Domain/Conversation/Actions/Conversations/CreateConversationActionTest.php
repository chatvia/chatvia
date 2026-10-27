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
use Domain\Conversation\Services\ChatStoragePath;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Guardrail\Enums\PiiFilterType;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CreateConversationAction is the single entry point for materialising a
 * conversation from the hosted-chat surface. The attachment cap matters for
 * security: a script that bypassed the composer could otherwise upload many
 * orphans before the conversation exists and claim them all here, defeating
 * MaxConversationAttachments on every later turn and running up document
 * reading costs.
 */
it('caps the number of attachments bound to a new conversation at config(chatvia.agent.files.upload.max_files)', function (): void {
    config(['chatvia.agent.files.upload.max_files' => 3]);

    $agent = Agent::factory()->create();
    $orphanIds = Attachment::factory()->count(50)->pending($agent)->create()->pluck('external_id')->all();

    // Only the claim is exercised here; the other collaborators are tested
    // elsewhere.
    $data = new CreateConversationData(
        scope: new ChatScopeData($agent, (string) Str::uuid7()),
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

it('moves the claimed files from the pending folder into the conversation\'s', function (): void {
    Storage::fake(Attachment::DISK);
    $agent = Agent::factory()->create();
    $orphan = Attachment::factory()->pending($agent)->stored()->create();
    $pendingPath = $orphan->filepath;

    $conversationId = app(CreateConversationAction::class)->execute(new CreateConversationData(
        scope: new ChatScopeData($agent, (string) Str::uuid7()),
        message: 'Hello',
        attachmentIds: [$orphan->external_id],
    ));

    $orphan->refresh();
    expect($orphan->filepath)->toStartWith(ChatStoragePath::forConversation($agent, $conversationId, 'files').'/')
        ->and($orphan->conversation->external_id)->toBe($conversationId);
    Storage::disk(Attachment::DISK)->assertExists($orphan->filepath);
    Storage::disk(Attachment::DISK)->assertMissing($pendingPath);
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

it('does NOT re-bind attachments that already belong to a different conversation', function (): void {
    config(['chatvia.agent.files.upload.max_files' => 100]);

    $agent = Agent::factory()->create();
    $otherConversation = Conversation::factory()->forAgent($agent)->create();
    // Attachment is bound to another conversation already.
    $alreadyBound = Attachment::factory()->forConversation($otherConversation)->create();

    $data = new CreateConversationData(
        scope: new ChatScopeData($agent, (string) Str::uuid7()),
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
    $agent->setSetting('filters', PiiFilterType::defaults(), 'guardrails');
    $sessionId = (string) Str::uuid7();

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
        ->and($history[0]->content)->toBe('Where is my order? Mail me at [EMAIL_1]');

    Bus::assertDispatched(StoreMessageJob::class, fn (StoreMessageJob $job): bool => $job->payload instanceof UserTurnData
        && $job->payload->message === 'Where is my order? Mail me at [EMAIL_1]'
        && $job->payload->message_for_llm === 'Where is my order? Mail me at [EMAIL_1]');
});
