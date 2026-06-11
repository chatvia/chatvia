<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\FeedbackGiven;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Conversation\Services\Memory\MessageManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

function makeMessageManager(ChatSession $session, Agent $agent): MessageManager
{
    return new MessageManager(new ChatCache($session->external_id, $agent));
}

/**
 * @return array{0: Conversation, 1: ChatSession, 2: Agent}
 */
function makeFeedbackConversation(): array
{
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::HostedChat,
        'title' => 'Feedback chat',
    ]);

    return [$conversation, $session, $agent];
}

it('persists feedback and fires analytics exactly once when the message is cached', function (): void {
    Event::fake([FeedbackGiven::class]);

    [$conversation, $session, $agent] = makeFeedbackConversation();

    $manager = makeMessageManager($session, $agent);
    $messageId = $manager->addMessage($conversation->external_id, 'assistant', 'Hello!');

    Message::create([
        'external_id' => $messageId,
        'chat_conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Hello!',
    ]);

    $manager->addFeedback($conversation->external_id, $messageId, 'positive');

    expect(Message::where('external_id', $messageId)->value('feedback'))->toBe('positive')
        ->and($manager->getMessageById($conversation->external_id, $messageId)?->feedback)->toBe('positive');

    // The warm path already wrote the DB row, so the cold-cache fallback's
    // guard must see feedback unchanged and NOT double-count analytics.
    Event::assertDispatchedTimes(FeedbackGiven::class, 1);
});

it('persists feedback straight to the DB and still fires analytics when the cache is cold', function (): void {
    // The conversation's cache entry has expired / been evicted: the
    // warm path (updateMessage) iterates an empty list and no-ops, so
    // only the DB fallback can record the feedback.
    Event::fake([FeedbackGiven::class]);

    [$conversation, $session, $agent] = makeFeedbackConversation();

    $messageId = (string) Str::uuid();
    Message::create([
        'external_id' => $messageId,
        'chat_conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Hello!',
    ]);

    makeMessageManager($session, $agent)
        ->addFeedback($conversation->external_id, $messageId, 'negative');

    expect(Message::where('external_id', $messageId)->value('feedback'))->toBe('negative');

    Event::assertDispatchedTimes(FeedbackGiven::class, 1);
    Event::assertDispatched(FeedbackGiven::class, fn (FeedbackGiven $event): bool => $event->feedback === 'negative'
        && $event->messageExternalId === $messageId
        && $event->conversationExternalId === $conversation->external_id
        // Model attribution lives only in the cache, so it's null here.
        && $event->llmExternalName === null);
});
