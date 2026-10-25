<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\ChatLog\ListConversationMessagesAction;
use Domain\Agent\Data\Resources\ChatLog\Messages\ChatLogMessageData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\MessageSource;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Illuminate\Support\Str;

/**
 * The chat-log message panel: the conversation's messages in order (a visitor
 * message before the reply stamped in the same second), its handoff state,
 * the knowledge each reply was given, and the latest SUBMITTED lead of that
 * conversation. A form that was shown but never filled is not a lead.
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

it('lists the knowledge a reply was given, best first, with a short preview of each chunk', function (): void {
    $agent = Agent::factory()->create();
    $conversation = lcmConversation($agent);
    $reply = Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => 'assistant']);
    $chunk = Chunk::factory()->create(['workspace_id' => $agent->workspace_id, 'chunk' => "Refunds   take\n five days. ".Str::repeat('word ', 200)]);

    MessageSource::query()->insert([
        ['workspace_id' => $agent->workspace_id, 'chat_message_id' => $reply->id, 'rank' => 1, 'chunk_id' => null, 'sourceable_type' => 'agent_website_url', 'sourceable_id' => 7, 'score' => 0.71, 'label' => 'Shipping', 'url' => 'https://example.com/shipping', 'page' => null],
        ['workspace_id' => $agent->workspace_id, 'chat_message_id' => $reply->id, 'rank' => 0, 'chunk_id' => $chunk->id, 'sourceable_type' => 'source_file', 'sourceable_id' => $chunk->chunkable_id, 'score' => 0.92, 'label' => 'Policy.pdf', 'url' => null, 'page' => 3],
    ]);

    $message = app(ListConversationMessagesAction::class)->execute($agent, $conversation)->messages->items()[0];
    [$best, $second] = $message->sources;

    expect($message->sources)->toHaveCount(2)
        ->and($best->rank)->toBe(0)
        ->and($best->blocks)->toBe([1])
        ->and($best->label)->toBe('Policy.pdf')
        ->and($best->kind)->toBe(SourceKind::File)
        ->and($best->page)->toBe(3)
        ->and($best->score)->toBe(0.92)
        ->and($best->preview)->toStartWith('Refunds take five days.')
        ->and(Str::length((string) $best->preview))->toBeLessThanOrEqual(403)
        ->and($second->kind)->toBe(SourceKind::Website)
        ->and($second->url)->toBe('https://example.com/shipping')
        ->and($second->preview)->toBeNull();
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

it('flags a reply for which no knowledge was found, but not a Q&A match', function (): void {
    $agent = Agent::factory()->create();
    $conversation = lcmConversation($agent);
    $at = now()->subMinutes(5);
    Message::factory()->fromAssistant()->create(['chat_conversation_id' => $conversation->id, 'has_llm_found_answer' => false, 'created_at' => $at]);
    Message::factory()->fromAssistant()->create(['chat_conversation_id' => $conversation->id, 'has_llm_found_answer' => false, 'is_response_from_qa' => true, 'created_at' => $at->copy()->addMinute()]);
    Message::factory()->fromAssistant()->create(['chat_conversation_id' => $conversation->id, 'has_llm_found_answer' => true, 'created_at' => $at->copy()->addMinutes(2)]);

    $flags = collect(app(ListConversationMessagesAction::class)->execute($agent, $conversation)->messages->items())
        ->map(fn (ChatLogMessageData $m): bool => $m->not_found)
        ->all();

    expect($flags)->toBe([true, false, false]);
});
