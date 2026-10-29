<?php

use Domain\Agent\Data\Resources\ChatLog\Messages\ChatLogMessageData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Queries\ConversationMessagesQuery;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;

/**
 * The chat-log message panel: the conversation's messages in order (a visitor
 * message before the reply stamped in the same second) and the latest
 * SUBMITTED lead of that conversation, which needs a license key. A
 * conversation of another agent shows nothing.
 */
it('returns the messages in order with the latest submitted lead, and no lead without a license key', function (): void {
    config(['chatvia.license.licensed' => true]);
    $agent = Agent::factory()->create();
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id]);
    $at = now()->subMinutes(5);
    Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Reply', 'created_at' => $at]);
    Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Question', 'created_at' => $at]);
    Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Follow-up', 'created_at' => $at->copy()->addMinute()]);
    $definition = FormDefinition::factory()->forAgent($agent)->create();
    FormSubmission::factory()->forDefinition($definition)->submitted()->create(['chat_conversation_id' => $conversation->id, 'values' => ['email' => 'old@example.com']]);
    $latest = FormSubmission::factory()->forDefinition($definition)->submitted()->create(['chat_conversation_id' => $conversation->id, 'values' => ['email' => 'new@example.com']]);
    FormSubmission::factory()->forDefinition($definition)->create(['chat_conversation_id' => $conversation->id]);

    $result = app(ConversationMessagesQuery::class)->get($agent, $conversation);
    config(['chatvia.license.licensed' => false]);
    $unlicensed = app(ConversationMessagesQuery::class)->get($agent, $conversation);

    expect(collect($result->messages->items())->map(fn (ChatLogMessageData $m): string => $m->content)->all())
        ->toBe(['Question', 'Reply', 'Follow-up'])
        ->and($result->conversation_title)->toBe('Question')
        ->and($result->handoff->status)->toBe(HandoffStatus::Ai->value)
        ->and($result->lead?->id)->toBe($latest->id)
        ->and($result->lead?->email)->toBe('new@example.com')
        ->and($unlicensed->lead)->toBeNull();
});

it('returns nothing for a conversation of another agent', function (): void {
    $agent = Agent::factory()->create();
    $foreign = Conversation::factory()->create(['agent_id' => Agent::factory()->create()->id]);
    Message::factory()->create(['chat_conversation_id' => $foreign->id, 'role' => 'user']);

    $result = app(ConversationMessagesQuery::class)->get($agent, $foreign);

    expect($result->messages->total())->toBe(0)
        ->and($result->conversation_title)->toBeNull();
});
