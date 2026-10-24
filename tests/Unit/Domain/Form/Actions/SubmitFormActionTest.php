<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Form\Actions\SubmitFormAction;
use Domain\Form\Data\SubmitFormData;
use Domain\Form\Enums\FormClaimFailure;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Pest\Expectation;

/**
 * Submitting an inline form: the submission is claimed once (a second submit,
 * or one after the time limit, is refused with its reason), the answers are
 * stored as plaintext under the schema's keys only, the message that showed
 * the form flips to submitted, and the same answers from a second render are
 * kept once.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->agent = makeTeamAgent($this->user, 'Bot');

    $session = Session::factory()->anonymous()->create(['agent_id' => $this->agent->id]);
    $this->conversation = Conversation::factory()->withTitle('Test')->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::HostedChat,
    ]);

    // Default factory is a lead form mapping name/email/message → lead.
    $this->definition = FormDefinition::factory()->forAgent($this->agent)->create();
});

function sfaPendingSubmission(object $test, ?Carbon $expiresAt = null): FormSubmission
{
    return FormSubmission::factory()->forDefinition($test->definition)->create([
        'chat_conversation_id' => $test->conversation->id,
        'status' => FormSubmissionStatus::Pending,
        'expires_at' => $expiresAt ?? now()->addHour(),
    ]);
}

/**
 * An assistant message showing the form instance as a pending form artifact.
 */
function sfaFormMessage(Conversation $conversation, FormSubmission $submission): Message
{
    return Message::factory()->fromAssistant()->withMetadata(['artifacts' => [
        ['kind' => 'form', 'data' => ['form_instance_id' => $submission->external_id, 'status' => 'pending']],
    ]])->create(['chat_conversation_id' => $conversation->id]);
}

it('submits a form: claims and stores the answers as plaintext', function (): void {
    $this->freezeTime();
    Event::fake([FormSubmitted::class]);
    $submission = sfaPendingSubmission($this);

    $result = app(SubmitFormAction::class)->execute(new SubmitFormData(
        submission: $submission,
        conversation: $this->conversation,
        values: ['name' => 'Jane', 'email' => 'jane@acme.com', 'message' => 'Hello'],
    ));

    expect($result->status)->toBe(FormSubmissionStatus::Submitted)
        // The submit endpoints send this result as the JSON response body.
        ->and($result->toArray())->toBe([
            'status' => 'submitted',
            'success_message' => 'Thanks — we will be in touch shortly.',
            'submitted_values' => ['name' => 'Jane', 'email' => 'jane@acme.com', 'message' => 'Hello'],
        ]);

    $submission->refresh();
    expect($submission->status)->toBe(FormSubmissionStatus::Submitted)
        ->and($submission->submitted_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($submission->email)->toBe('jane@acme.com')
        ->and($submission->name)->toBe('Jane');

    // Answers are stored as plaintext JSON — no encryption cast.
    $raw = DB::table('form_submissions')->where('id', $submission->id)->value('values');
    expect(json_decode($raw, true))->toMatchArray(['name' => 'Jane', 'email' => 'jane@acme.com']);

    Event::assertDispatchedTimes(FormSubmitted::class, 1);
});

it('rejects a double submit (already submitted)', function (): void {
    $submission = sfaPendingSubmission($this);
    app(SubmitFormAction::class)->execute(new SubmitFormData($submission, $this->conversation, ['name' => 'Jane', 'email' => 'jane@acme.com']));

    app(SubmitFormAction::class)->execute(new SubmitFormData($submission->fresh(), $this->conversation, ['name' => 'Jane', 'email' => 'jane@acme.com']));
})->throws(FormNotClaimableException::class);

it('rejects an expired form as expired', function (): void {
    $submission = sfaPendingSubmission($this, now()->subHour());

    expect(fn () => app(SubmitFormAction::class)->execute(new SubmitFormData($submission, $this->conversation, ['name' => 'Jane', 'email' => 'jane@acme.com'])))
        ->toThrow(fn (FormNotClaimableException $e): Expectation => expect($e->reason)->toBe(FormClaimFailure::Expired))
        ->and($submission->fresh()->status)->toBe(FormSubmissionStatus::Pending);
});

it('flips the owning message artifact to submitted for reload state', function (): void {
    $submission = sfaPendingSubmission($this);
    $message = sfaFormMessage($this->conversation, $submission);

    app(SubmitFormAction::class)->execute(new SubmitFormData(
        submission: $submission,
        conversation: $this->conversation,
        values: ['name' => 'Jane', 'email' => 'jane@acme.com'],
        assistantMessageExternalId: $message->external_id,
    ));

    $metadata = $message->fresh()->metadata;
    expect($metadata['artifacts'][0]['data']['status'])->toBe('submitted')
        ->and($submission->fresh()->chat_message_id)->toBe($message->id);
});

it('links no message from outside the submission\'s conversation', function (string $where): void {
    $submission = sfaPendingSubmission($this);
    $foreignConversation = $where === 'another workspace'
        ? Conversation::factory()->create()
        : Conversation::factory()->create(['agent_id' => $this->agent->id]);
    $foreign = sfaFormMessage($foreignConversation, $submission);

    app(SubmitFormAction::class)->execute(new SubmitFormData(
        submission: $submission,
        conversation: $this->conversation,
        values: ['name' => 'Jane', 'email' => 'jane@acme.com'],
        assistantMessageExternalId: $foreign->external_id,
    ));

    expect($submission->fresh()->status)->toBe(FormSubmissionStatus::Submitted)
        ->and($submission->fresh()->chat_message_id)->toBeNull()
        ->and($foreign->fresh()->metadata['artifacts'][0]['data']['status'])->toBe('pending');
})->with(['another conversation of the agent', 'another workspace']);

it('de-dupes identical values submitted from a second form render', function (): void {
    Event::fake([FormSubmitted::class]);
    $first = sfaPendingSubmission($this);
    $second = sfaPendingSubmission($this); // a second render of the same form
    $values = ['name' => 'Jane', 'email' => 'jane@acme.com', 'message' => 'Hello'];

    app(SubmitFormAction::class)->execute(new SubmitFormData($first, $this->conversation, $values));
    $result = app(SubmitFormAction::class)->execute(new SubmitFormData($second->fresh(), $this->conversation, $values));

    // The visitor still sees success, but no duplicate row is kept: only the
    // first remains submitted and the duplicate instance was dropped.
    expect($result->status)->toBe(FormSubmissionStatus::Submitted)
        ->and(FormSubmission::query()->where('form_definition_id', $this->definition->id)->where('status', 'submitted')->count())->toBe(1)
        ->and(FormSubmission::query()->whereKey($second->id)->exists())->toBeFalse();

    Event::assertDispatchedTimes(FormSubmitted::class, 1); // only the first
});

it('keeps a second submission whose values differ', function (): void {
    $first = sfaPendingSubmission($this);
    $second = sfaPendingSubmission($this);

    app(SubmitFormAction::class)->execute(new SubmitFormData($first, $this->conversation, ['name' => 'Jane', 'email' => 'jane@acme.com']));
    app(SubmitFormAction::class)->execute(new SubmitFormData($second->fresh(), $this->conversation, ['name' => 'John', 'email' => 'john@acme.com']));

    expect(FormSubmission::query()->where('form_definition_id', $this->definition->id)->where('status', 'submitted')->count())->toBe(2);
});

it('drops field keys not declared in the form schema', function (): void {
    $submission = sfaPendingSubmission($this);

    app(SubmitFormAction::class)->execute(new SubmitFormData(
        submission: $submission,
        conversation: $this->conversation,
        values: ['name' => 'Jane', 'email' => 'jane@acme.com', 'evil' => 'x'],
    ));

    // 'evil' is not a field of the form, so it is not stored; every declared
    // field that was sent (here name and email) is.
    expect($submission->fresh()->values)->toEqual(['name' => 'Jane', 'email' => 'jane@acme.com']);
});
