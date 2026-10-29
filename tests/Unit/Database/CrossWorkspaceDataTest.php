<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The database keeps workspaces apart. Every chat row carries its workspace,
 * and each key to a parent pairs it with that workspace. So even code that
 * forgets a check cannot write a session, conversation, message, lead or
 * chatter grant that links two workspaces: Postgres refuses the row. A lead
 * outlives the chat rows it points at, and deleting a workspace takes only
 * its own chat rows.
 */
beforeEach(function (): void {
    $this->agentA = Agent::factory()->create();
    $this->agentB = Agent::factory()->create();
});

/**
 * Runs $write in a savepoint, so the refused insert leaves the test's
 * transaction usable, and expects Postgres to refuse it for a foreign key
 * (SQLSTATE 23503), not for any other reason such as a missing column.
 */
function cwdRefused(Closure $write): void
{
    expect(fn (): mixed => DB::transaction($write))->toThrow(function (QueryException $exception): void {
        expect($exception->getCode())->toBe('23503');
    });
}

it('accepts those rows within one workspace, and keeps a lead when its message and then its conversation are deleted, clearing only each link', function (): void {
    $workspaceId = $this->agentA->workspace_id;
    $chatter = Chatter::factory()->ofWorkspace($this->agentA->workspace)->create();
    $form = FormDefinition::factory()->forAgent($this->agentA)->create();

    $session = Session::query()->create(['workspace_id' => $workspaceId, 'agent_id' => $this->agentA->id, 'chatter_id' => $chatter->id]);
    $conversation = Conversation::query()->create(['workspace_id' => $workspaceId, 'chat_session_id' => $session->id, 'agent_id' => $this->agentA->id, 'source' => ConversationSource::Widget]);
    $message = Message::query()->create(['workspace_id' => $workspaceId, 'chat_conversation_id' => $conversation->id, 'role' => MessageRole::User, 'content' => 'Hello']);
    $lead = FormSubmission::query()->create([
        'form_definition_id' => $form->id,
        'workspace_id' => $workspaceId,
        'agent_id' => $this->agentA->id,
        'chat_conversation_id' => $conversation->id,
        'chat_message_id' => $message->id,
        'status' => FormSubmissionStatus::Pending,
    ]);
    $chatter->agents()->attach($this->agentA->id, ['workspace_id' => $workspaceId]);

    expect($message->exists)->toBeTrue()
        ->and($lead->exists)->toBeTrue()
        ->and($chatter->agents()->whereKey($this->agentA->id)->exists())->toBeTrue();

    $message->forceDelete();

    expect($lead->fresh())
        ->not->toBeNull()
        ->chat_message_id->toBeNull()
        ->chat_conversation_id->toBe($conversation->id);

    $conversation->forceDelete();

    expect($lead->fresh())
        ->not->toBeNull()
        ->chat_conversation_id->toBeNull()
        ->workspace_id->toBe($workspaceId);
});

it('refuses a session, conversation, message, lead or chatter grant whose keys point into another workspace', function (): void {
    $chatterA = Chatter::factory()->ofWorkspace($this->agentA->workspace)->create();
    $chatterB = Chatter::factory()->ofWorkspace($this->agentB->workspace)->create();
    $sessionA = Session::factory()->create(['agent_id' => $this->agentA->id]);
    $conversationA = Conversation::factory()->create(['agent_id' => $this->agentA->id]);
    $messageA = Message::factory()->create(['chat_conversation_id' => $conversationA->id]);
    $formB = FormDefinition::factory()->forAgent($this->agentB)->create();

    cwdRefused(fn (): Session => Session::query()->create([
        'workspace_id' => $this->agentB->workspace_id,
        'agent_id' => $this->agentA->id,
    ]));
    cwdRefused(fn (): Session => Session::query()->create([
        'workspace_id' => $this->agentA->workspace_id,
        'agent_id' => $this->agentA->id,
        'chatter_id' => $chatterB->id,
    ]));
    cwdRefused(fn (): Conversation => Conversation::query()->create([
        'workspace_id' => $this->agentB->workspace_id,
        'chat_session_id' => $sessionA->id,
        'agent_id' => $this->agentB->id,
        'source' => ConversationSource::Widget,
    ]));
    cwdRefused(fn (): Message => Message::query()->create([
        'workspace_id' => $this->agentB->workspace_id,
        'chat_conversation_id' => $conversationA->id,
        'role' => MessageRole::User,
        'content' => 'Hello',
    ]));
    cwdRefused(fn (): FormSubmission => FormSubmission::query()->create([
        'form_definition_id' => $formB->id,
        'workspace_id' => $this->agentB->workspace_id,
        'agent_id' => $this->agentB->id,
        'chat_conversation_id' => $conversationA->id,
        'status' => FormSubmissionStatus::Pending,
    ]));
    cwdRefused(fn (): FormSubmission => FormSubmission::query()->create([
        'form_definition_id' => $formB->id,
        'workspace_id' => $this->agentB->workspace_id,
        'agent_id' => $this->agentB->id,
        'chat_message_id' => $messageA->id,
        'status' => FormSubmissionStatus::Pending,
    ]));
    cwdRefused(fn (): null => $chatterA->agents()->attach($this->agentB->id, ['workspace_id' => $chatterA->workspace_id]));
    cwdRefused(fn (): null => $chatterA->agents()->attach($this->agentB->id, ['workspace_id' => $this->agentB->workspace_id]));
});

it('deletes a workspace\'s chat rows with it and leaves other workspaces alone', function (): void {
    $messageA = Message::factory()->create([
        'chat_conversation_id' => Conversation::factory()->create(['agent_id' => $this->agentA->id])->id,
    ]);
    $messageB = Message::factory()->create([
        'chat_conversation_id' => Conversation::factory()->create(['agent_id' => $this->agentB->id])->id,
    ]);

    $this->agentA->workspace->delete();

    expect(Message::query()->whereKey($messageA->id)->exists())->toBeFalse()
        ->and(Message::query()->whereKey($messageB->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($this->agentB->workspace->owner?->id)->exists())->toBeTrue();
});
