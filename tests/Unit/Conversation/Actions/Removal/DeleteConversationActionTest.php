<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Removal\DeleteConversationAction;
use Domain\Conversation\Actions\Removal\DeleteSessionConversationsAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session as ChatSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Erasing a conversation erases the message text at once (bulk updates); the
 * rows themselves go later with the trash purge.
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

/**
 * @return array{0: Agent, 1: ChatSession, 2: Conversation, 3: Message}
 */
function erasureSetup(): array
{
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id]);

    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'workspace_id' => $agent->workspace_id,
        'agent_id' => $agent->id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::HostedChat,
        'title' => 'Card question',
        'is_visible' => true,
    ]);

    $message = Message::create([
        'external_id' => (string) Str::uuid(),
        'workspace_id' => $conversation->workspace_id,
        'chat_conversation_id' => $conversation->id,
        'role' => MessageRole::User,
        'content' => 'My card number is 4242 4242 4242 4242',
    ]);

    return [$agent, $session, $conversation, $message];
}

function erasureScope(ChatSession $session, Agent $agent, ?string $conversationId = null): ChatScopeData
{
    return new ChatScopeData($agent, $session->external_id, $conversationId);
}

function erasureRow(Message $message): object
{
    return DB::table('chat_messages')->where('id', $message->id)->first(['content']);
}

it('erases the text when one conversation is erased', function (): void {
    [$agent, $session, $conversation, $message] = erasureSetup();
    expect(erasureRow($message)->content)->not->toBeNull();

    app(DeleteConversationAction::class)->execute(erasureScope($session, $agent, $conversation->external_id), 'admin');

    $row = erasureRow($message);
    expect($row->content)->toBeNull()
        ->and(Conversation::withTrashed()->find($conversation->id)->trashed())->toBeTrue();
});

it('erases the text when every conversation of a session is erased', function (): void {
    [$agent, $session, , $message] = erasureSetup();

    app(DeleteSessionConversationsAction::class)->execute(erasureScope($session, $agent), 'user');

    expect(erasureRow($message)->content)->toBeNull();
});

it('treats erasing an already-deleted conversation as a no-op', function (): void {
    [$agent, $session, $conversation] = erasureSetup();
    $delete = app(DeleteConversationAction::class);

    $delete->execute(erasureScope($session, $agent, $conversation->external_id));

    // A repeated request must not crash on the missing (soft-deleted) row.
    $delete->execute(erasureScope($session, $agent, $conversation->external_id));
    $delete->execute(erasureScope($session, $agent, (string) Str::uuid()));

    expect(Conversation::withTrashed()->find($conversation->id)->trashed())->toBeTrue();
});
