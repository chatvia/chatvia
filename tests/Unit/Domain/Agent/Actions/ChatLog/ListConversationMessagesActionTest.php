<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\ChatLog\ListConversationMessagesAction;
use Domain\Agent\Data\Resources\ChatLog\Messages\ChatLogMessageData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;

/**
 * The chat-log message panel: the conversation's messages in order (a visitor
 * message before the reply stamped in the same second), its handoff state,
 * and the latest SUBMITTED lead of that conversation. A form that was shown
 * but never filled is not a lead.
 */
function lcmConversation(Agent $agent): Conversation
{
    return Conversation::factory()->create(['agent_id' => $agent->id]);
}

it('returns the messages in order, a visitor message before a same-second reply', function (): void {
    $agent = Agent::factory()->create();
    $conversation = lcmConversation($agent);
    $at = now()->subMinutes(5);

    Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Reply', 'created_at' => $at]);
    Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Question', 'created_at' => $at]);
    Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Follow-up', 'created_at' => $at->copy()->addMinute()]);

    $result = app(ListConversationMessagesAction::class)->execute($agent, $conversation);

    expect(collect($result->messages->items())->map(fn (ChatLogMessageData $m): string => $m->content)->all())
        ->toBe(['Question', 'Reply', 'Follow-up'])
        ->and($result->conversation_title)->toBe('Question')
        ->and($result->handoff->status)->toBe(HandoffStatus::Ai->value);
});

it('keeps same-second messages of one role in the order they were stored, across pages', function (): void {
    $agent = Agent::factory()->create();
    $conversation = lcmConversation($agent);
    $at = now()->subMinutes(5)->startOfSecond();
    $stored = Message::factory()->count(60)->create(['chat_conversation_id' => $conversation->id, 'role' => 'user', 'created_at' => $at]);

    $first = app(ListConversationMessagesAction::class)->execute($agent, $conversation, page: 1);
    $second = app(ListConversationMessagesAction::class)->execute($agent, $conversation, page: 2);
    $paged = collect([...$first->messages->items(), ...$second->messages->items()])->map(fn (ChatLogMessageData $m): string => $m->id)->all();

    expect($paged)->toBe($stored->pluck('id')->map(fn (int $id): string => (string) $id)->all());
});

it('pages the messages fifty at a time', function (): void {
    $agent = Agent::factory()->create();
    $conversation = lcmConversation($agent);
    Message::factory()->count(51)->create(['chat_conversation_id' => $conversation->id, 'role' => 'user']);

    $second = app(ListConversationMessagesAction::class)->execute($agent, $conversation, page: 2);

    expect($second->messages->total())->toBe(51)
        ->and($second->messages->currentPage())->toBe(2)
        ->and($second->messages->items())->toHaveCount(1);
});

it('returns nothing for a conversation of another agent', function (): void {
    $agent = Agent::factory()->create();
    $foreign = lcmConversation(Agent::factory()->create());
    Message::factory()->create(['chat_conversation_id' => $foreign->id, 'role' => 'user']);

    $result = app(ListConversationMessagesAction::class)->execute($agent, $foreign);

    expect($result->messages->total())->toBe(0)
        ->and($result->conversation_title)->toBeNull();
});

it('attaches the latest submitted lead and ignores pending forms', function (): void {
    $agent = Agent::factory()->create();
    $conversation = lcmConversation($agent);
    $definition = FormDefinition::factory()->forAgent($agent)->create();

    FormSubmission::factory()->forDefinition($definition)->submitted()->create([
        'chat_conversation_id' => $conversation->id,
        'values' => ['email' => 'old@example.com'],
    ]);
    $latest = FormSubmission::factory()->forDefinition($definition)->submitted()->create([
        'chat_conversation_id' => $conversation->id,
        'values' => ['email' => 'new@example.com'],
    ]);
    FormSubmission::factory()->forDefinition($definition)->create(['chat_conversation_id' => $conversation->id]);

    $lead = app(ListConversationMessagesAction::class)->execute($agent, $conversation)->lead;

    expect($lead?->id)->toBe($latest->id)
        ->and($lead?->email)->toBe('new@example.com');
});

it('has no lead when the only form was never filled', function (): void {
    $agent = Agent::factory()->create();
    $conversation = lcmConversation($agent);
    $definition = FormDefinition::factory()->forAgent($agent)->create();
    FormSubmission::factory()->forDefinition($definition)->create(['chat_conversation_id' => $conversation->id]);

    expect(app(ListConversationMessagesAction::class)->execute($agent, $conversation)->lead)->toBeNull();
});

it('reports a live handoff with the operator', function (): void {
    $agent = Agent::factory()->create();
    $conversation = lcmConversation($agent);
    $operator = User::factory()->create();
    $conversation->forceFill([
        'handoff_status' => HandoffStatus::Human,
        'handoff_user_id' => $operator->id,
        'handoff_at' => now(),
    ])->save();

    $handoff = app(ListConversationMessagesAction::class)->execute($agent, $conversation->fresh())->handoff;

    expect($handoff->status)->toBe(HandoffStatus::Human->value)
        ->and($handoff->admin_id)->toBe($operator->id)
        ->and($handoff->admin_name)->toBe($operator->name);
});
