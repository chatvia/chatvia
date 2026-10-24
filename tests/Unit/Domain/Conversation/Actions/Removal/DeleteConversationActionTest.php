<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Removal\DeleteConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Erasing a conversation erases the message text at once (bulk updates) and
 * records who did it; the rows themselves go later with the trash purge. Only
 * a conversation of the given session and agent is erased.
 */
beforeEach(function (): void {
    $this->conversation = Conversation::factory()->hostedChat()->visible()->withTitle('Card question')->create();
    $this->message = Message::factory()->fromUser()->create([
        'chat_conversation_id' => $this->conversation->id,
        'content' => 'My card number is 4242 4242 4242 4242',
    ]);
});

/** The visitor's scope of the conversation, unless another session or agent is given. */
function dcaScope(Conversation $conversation, ?Session $session = null, ?Agent $agent = null): ChatScopeData
{
    return new ChatScopeData($agent ?? $conversation->agent, ($session ?? $conversation->session)->external_id, $conversation->external_id);
}

function dcaStoredText(Message $message): ?string
{
    return DB::table('chat_messages')->where('id', $message->id)->value('content');
}

it('erases the text of the conversation and records who deleted it', function (): void {
    app(DeleteConversationAction::class)->execute(dcaScope($this->conversation), 'admin');

    $trashed = Conversation::withTrashed()->findOrFail($this->conversation->id);
    expect(dcaStoredText($this->message))->toBeNull()
        ->and($trashed->trashed())->toBeTrue()
        ->and($trashed->deleted_by)->toBe('admin');
});

it('treats erasing an already-deleted conversation as a no-op', function (): void {
    $delete = app(DeleteConversationAction::class);

    $delete->execute(dcaScope($this->conversation));

    // A repeated request must not crash on the missing (soft-deleted) row.
    $delete->execute(dcaScope($this->conversation));
    $delete->execute(new ChatScopeData($this->conversation->agent, $this->conversation->session->external_id, (string) Str::uuid()));

    expect(Conversation::withTrashed()->find($this->conversation->id)->trashed())->toBeTrue();
});

it('leaves a conversation of another session or agent alone', function (): void {
    $delete = app(DeleteConversationAction::class);

    // The conversation id replayed from another visitor's session, and under another agent.
    $delete->execute(dcaScope($this->conversation, session: Session::factory()->create(['agent_id' => $this->conversation->agent_id])), 'user');
    $delete->execute(dcaScope($this->conversation, agent: Agent::factory()->create()), 'user');

    $row = Conversation::withTrashed()->find($this->conversation->id);

    expect(dcaStoredText($this->message))->toBe('My card number is 4242 4242 4242 4242')
        ->and($row->trashed())->toBeFalse()
        ->and($row->deleted_by)->toBeNull();
});
