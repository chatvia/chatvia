<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Form\Actions\SubmitFormAction;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->agent = makeTeamAgent($this->user, 'Bot');

    $session = Session::create(['agent_id' => $this->agent->id, 'user_id' => null]);
    $this->conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $this->agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Test',
    ]);

    // Default factory is a lead form mapping name/email/message → lead.
    $this->definition = FormDefinition::factory()->forAgent($this->agent)->create();
});

function pendingFormSubmission(object $test, ?Carbon $expiresAt = null): FormSubmission
{
    return FormSubmission::factory()->forDefinition($test->definition)->create([
        'chat_conversation_id' => $test->conversation->id,
        'status' => FormSubmission::STATUS_PENDING,
        'expires_at' => $expiresAt ?? now()->addHour(),
    ]);
}

it('submits a form: claims and stores the (encrypted) answers', function (): void {
    Event::fake([FormSubmitted::class]);
    $submission = pendingFormSubmission($this);

    $result = app(SubmitFormAction::class)->execute(
        $submission,
        $this->conversation,
        ['name' => 'Jane', 'email' => 'jane@acme.com', 'message' => 'Hello'],
    );

    expect($result['status'])->toBe('submitted');

    $submission->refresh();
    expect($submission->status)->toBe(FormSubmission::STATUS_SUBMITTED)
        ->and($submission->submitted_at)->not->toBeNull()
        ->and($submission->email)->toBe('jane@acme.com')   // accessor over decrypted values
        ->and($submission->name)->toBe('Jane');

    Event::assertDispatchedTimes(FormSubmitted::class, 1);
});

it('rejects a double submit (already submitted)', function (): void {
    $submission = pendingFormSubmission($this);
    app(SubmitFormAction::class)->execute($submission, $this->conversation, ['name' => 'Jane', 'email' => 'jane@acme.com']);

    app(SubmitFormAction::class)->execute($submission->fresh(), $this->conversation, ['name' => 'Jane', 'email' => 'jane@acme.com']);
})->throws(FormNotClaimableException::class);

it('rejects an expired form with a 410 status', function (): void {
    $submission = pendingFormSubmission($this, now()->subHour());

    try {
        app(SubmitFormAction::class)->execute($submission, $this->conversation, ['name' => 'Jane', 'email' => 'jane@acme.com']);
        $this->fail('Expected FormNotClaimableException');
    } catch (FormNotClaimableException $e) {
        expect($e->status)->toBe(410);
    }
});

it('flips the owning message artifact to submitted for reload state', function (): void {
    $submission = pendingFormSubmission($this);
    $message = Message::create([
        'chat_conversation_id' => $this->conversation->id,
        'role' => 'assistant',
        'message_credits' => 0,
        'content' => 'Here is a form',
        'metadata' => ['artifacts' => [
            ['kind' => 'form', 'data' => ['form_instance_id' => $submission->external_id, 'status' => 'pending']],
        ]],
    ]);

    app(SubmitFormAction::class)->execute(
        $submission,
        $this->conversation,
        ['name' => 'Jane', 'email' => 'jane@acme.com'],
        null,
        null,
        null,
        $message->external_id,
    );

    $metadata = $message->fresh()->metadata;
    expect($metadata['artifacts'][0]['data']['status'])->toBe('submitted')
        ->and($submission->fresh()->chat_message_id)->toBe($message->id);
});

it('de-dupes identical values submitted from a second form render', function (): void {
    Event::fake([FormSubmitted::class]);
    $first = pendingFormSubmission($this);
    $second = pendingFormSubmission($this); // a second render of the same form
    $values = ['name' => 'Jane', 'email' => 'jane@acme.com', 'message' => 'Hello'];

    app(SubmitFormAction::class)->execute($first, $this->conversation, $values);
    $result = app(SubmitFormAction::class)->execute($second->fresh(), $this->conversation, $values);

    // The visitor still sees success, but no duplicate row is kept: only the
    // first remains submitted and the duplicate instance was dropped.
    expect($result['status'])->toBe('submitted')
        ->and(FormSubmission::query()->where('form_definition_id', $this->definition->id)->where('status', 'submitted')->count())->toBe(1)
        ->and(FormSubmission::query()->whereKey($second->id)->exists())->toBeFalse();

    Event::assertDispatchedTimes(FormSubmitted::class, 1); // only the first
});

it('keeps a second submission whose values differ', function (): void {
    $first = pendingFormSubmission($this);
    $second = pendingFormSubmission($this);

    app(SubmitFormAction::class)->execute($first, $this->conversation, ['name' => 'Jane', 'email' => 'jane@acme.com']);
    app(SubmitFormAction::class)->execute($second->fresh(), $this->conversation, ['name' => 'John', 'email' => 'john@acme.com']);

    expect(FormSubmission::query()->where('form_definition_id', $this->definition->id)->where('status', 'submitted')->count())->toBe(2);
});

it('drops field keys not declared in the form schema', function (): void {
    $submission = pendingFormSubmission($this);

    app(SubmitFormAction::class)->execute(
        $submission,
        $this->conversation,
        ['name' => 'Jane', 'email' => 'jane@acme.com', 'evil' => 'x'],
    );

    // 'evil' is not in the definition schema and is lead-mapped-free → stored
    // values exclude it (and exclude lead-mapped name/email/message too).
    expect($submission->fresh()->values ?? [])->not->toHaveKey('evil');
});
