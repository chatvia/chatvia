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

/*
|--------------------------------------------------------------------------
| The database keeps workspaces apart
|--------------------------------------------------------------------------
|
| Every chat row carries its workspace, and each key to a parent pairs it
| with that workspace. So even code that forgets a check cannot write a
| session, conversation, message, lead or chatter grant that links two
| workspaces: Postgres refuses the row.
*/

beforeEach(function (): void {
    $this->agentA = Agent::factory()->create();
    $this->agentB = Agent::factory()->create();
});

/** Runs $write in a savepoint, so the refused insert leaves the test's transaction usable. */
function refusedByDatabase(Closure $write): void
{
    expect(fn () => DB::transaction($write))->toThrow(QueryException::class);
}

it('refuses a session whose agent is in another workspace', function (): void {
    refusedByDatabase(fn () => Session::query()->create([
        'workspace_id' => $this->agentB->workspace_id,
        'agent_id' => $this->agentA->id,
    ]));
});

it('refuses a session whose chatter is in another workspace', function (): void {
    $chatterB = Chatter::factory()->ofWorkspace($this->agentB->workspace)->create();

    refusedByDatabase(fn () => Session::query()->create([
        'workspace_id' => $this->agentA->workspace_id,
        'agent_id' => $this->agentA->id,
        'chatter_id' => $chatterB->id,
    ]));
});

it('refuses a conversation whose session is in another workspace', function (): void {
    $sessionA = Session::factory()->create(['agent_id' => $this->agentA->id]);

    refusedByDatabase(fn () => Conversation::query()->create([
        'workspace_id' => $this->agentB->workspace_id,
        'chat_session_id' => $sessionA->id,
        'agent_id' => $this->agentB->id,
        'source' => ConversationSource::Widget,
    ]));
});

it('refuses a message whose conversation is in another workspace', function (): void {
    $conversationA = Conversation::factory()->create(['agent_id' => $this->agentA->id]);

    refusedByDatabase(fn () => Message::query()->create([
        'workspace_id' => $this->agentB->workspace_id,
        'chat_conversation_id' => $conversationA->id,
        'role' => MessageRole::User,
        'content' => 'Hello',
    ]));
});

it('refuses a lead that points at another workspace\'s conversation', function (): void {
    $formB = FormDefinition::factory()->forAgent($this->agentB)->create();
    $conversationA = Conversation::factory()->create(['agent_id' => $this->agentA->id]);

    refusedByDatabase(fn () => FormSubmission::query()->create([
        'form_definition_id' => $formB->id,
        'workspace_id' => $this->agentB->workspace_id,
        'agent_id' => $this->agentB->id,
        'chat_conversation_id' => $conversationA->id,
        'status' => FormSubmissionStatus::Pending,
    ]));
});

it('keeps a lead when its conversation is deleted and clears only the link', function (): void {
    $form = FormDefinition::factory()->forAgent($this->agentA)->create();
    $conversation = Conversation::factory()->create(['agent_id' => $this->agentA->id]);
    $lead = FormSubmission::factory()->forDefinition($form)->create(['chat_conversation_id' => $conversation->id]);

    $conversation->forceDelete();

    $lead->refresh();

    expect($lead->chat_conversation_id)->toBeNull()
        ->and($lead->workspace_id)->toBe($this->agentA->workspace_id);
});

it('refuses a chatter grant to an agent of another workspace', function (): void {
    $chatterA = Chatter::factory()->ofWorkspace($this->agentA->workspace)->create();

    refusedByDatabase(fn () => $chatterA->agents()->attach($this->agentB->id, ['workspace_id' => $chatterA->workspace_id]));
    refusedByDatabase(fn () => $chatterA->agents()->attach($this->agentB->id, ['workspace_id' => $this->agentB->workspace_id]));
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
