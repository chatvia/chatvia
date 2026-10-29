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
 * stored as plaintext under the schema's keys only, and only the message of
 * the submission's own conversation that showed the form flips to submitted.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->agent = makeTeamAgent($this->user, 'Bot');

    $session = Session::factory()->anonymous()->create(['agent_id' => $this->agent->id]);
    $this->conversation = Conversation::factory()->withTitle('Test')->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::HostedChat,
    ]);

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

it('claims the form once and stores only the declared answers as plaintext', function (): void {
    $this->freezeTime();
    Event::fake([FormSubmitted::class]);
    $submission = sfaPendingSubmission($this);

    $result = app(SubmitFormAction::class)->execute(new SubmitFormData(
        submission: $submission,
        conversation: $this->conversation,
        values: ['name' => 'Jane', 'email' => 'jane@acme.com', 'evil' => 'x'],
    ));

    $raw = DB::table('form_submissions')->where('id', $submission->id)->value('values');

    expect($result->status)->toBe(FormSubmissionStatus::Submitted)
        ->and($submission->fresh()->status)->toBe(FormSubmissionStatus::Submitted)
        ->and($submission->fresh()->submitted_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($submission->fresh()->values)->toEqual(['name' => 'Jane', 'email' => 'jane@acme.com'])
        ->and(json_decode($raw, true))->toEqual(['name' => 'Jane', 'email' => 'jane@acme.com'])
        ->and(fn () => app(SubmitFormAction::class)->execute(new SubmitFormData($submission->fresh(), $this->conversation, ['name' => 'Jane', 'email' => 'jane@acme.com'])))
        ->toThrow(FormNotClaimableException::class);
    Event::assertDispatchedTimes(FormSubmitted::class, 1);
});

it('rejects an expired form as expired', function (): void {
    $submission = sfaPendingSubmission($this, now()->subHour());

    expect(fn () => app(SubmitFormAction::class)->execute(new SubmitFormData($submission, $this->conversation, ['name' => 'Jane', 'email' => 'jane@acme.com'])))
        ->toThrow(fn (FormNotClaimableException $e): Expectation => expect($e->reason)->toBe(FormClaimFailure::Expired))
        ->and($submission->fresh()->status)->toBe(FormSubmissionStatus::Pending);
});

it('flips only a message of the submission\'s own conversation to submitted', function (string $where, bool $linked): void {
    $submission = sfaPendingSubmission($this);
    $conversation = match ($where) {
        'own conversation' => $this->conversation,
        'another conversation of the agent' => Conversation::factory()->create(['agent_id' => $this->agent->id]),
        'another workspace' => Conversation::factory()->create(),
    };
    $message = sfaFormMessage($conversation, $submission);

    app(SubmitFormAction::class)->execute(new SubmitFormData(
        submission: $submission,
        conversation: $this->conversation,
        values: ['name' => 'Jane', 'email' => 'jane@acme.com'],
        assistantMessageExternalId: $message->external_id,
    ));

    expect($submission->fresh()->status)->toBe(FormSubmissionStatus::Submitted)
        ->and($submission->fresh()->chat_message_id)->toBe($linked ? $message->id : null)
        ->and($message->fresh()->metadata['artifacts'][0]['data']['status'])->toBe($linked ? 'submitted' : 'pending');
})->with([
    'own conversation' => ['own conversation', true],
    'another conversation of the agent' => ['another conversation of the agent', false],
    'another workspace' => ['another workspace', false],
]);
