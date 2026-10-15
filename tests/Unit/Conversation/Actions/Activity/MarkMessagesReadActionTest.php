<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Activity\MarkMessagesReadAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageReader;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A conversation with two messages per role, cached and stored.
 *
 * @return array{0: ChatScopeData, 1: array<string, list<string>>}
 */
function readReceiptConversation(): array
{
    $agent = Agent::factory()->create();
    $session = ChatSession::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'source' => ConversationSource::Widget]);
    $scope = new ChatScopeData($agent, $session->external_id, $conversation->external_id);
    $memory = app(ChatMemory::class)->withScope($scope);

    $ids = [];
    foreach ([MessageRole::User, MessageRole::Operator, MessageRole::User, MessageRole::Operator] as $role) {
        $id = (string) Str::uuid();
        $memory->appendMessage(new MessageData(id: $id, role: $role, content: 'Hi', type: MessageData::TYPE_TEXT, timestamp: now()->timestamp));
        Message::factory()->create(['external_id' => $id, 'chat_conversation_id' => $conversation->id, 'role' => $role, 'content' => 'Hi']);
        $ids[$role->value][] = $id;
    }

    return [$scope, $ids];
}

it('marks the operator replies read for the visitor with a single update', function (): void {
    [$scope, $ids] = readReceiptConversation();

    DB::enableQueryLog();
    $marked = app(MarkMessagesReadAction::class)->execute($scope, MessageReader::Visitor);
    $updates = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_starts_with($q['query'], 'update "chat_messages"'));
    DB::disableQueryLog();

    expect($marked)->toBe(2)
        ->and($updates)->toHaveCount(1)
        ->and(Message::query()->whereIn('external_id', $ids['agent'])->whereNull('read_at')->count())->toBe(0)
        ->and(Message::query()->whereIn('external_id', $ids['user'])->whereNotNull('read_at')->count())->toBe(0);

    // Already read: nothing left to mark, no write.
    expect(app(MarkMessagesReadAction::class)->execute($scope, MessageReader::Visitor))->toBe(0);
});

it('marks the visitor messages read for the operator', function (): void {
    [$scope, $ids] = readReceiptConversation();

    expect(app(MarkMessagesReadAction::class)->execute($scope, MessageReader::Operator))->toBe(2)
        ->and(Message::query()->whereIn('external_id', $ids['user'])->whereNull('read_at')->count())->toBe(0)
        ->and(Message::query()->whereIn('external_id', $ids['agent'])->whereNotNull('read_at')->count())->toBe(0);
});
