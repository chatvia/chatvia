<?php

use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Form\Actions\RenderInlineFormAction;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Enums\FormType;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;

/**
 * Rendering a form resolves the model's request (slug, display name or
 * snake_case slug) against the agent's own active forms, refuses a form this
 * visitor's session already submitted or a capability manages, and otherwise
 * issues a pending form instance the visitor submits into.
 */
pest()->beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 6, 15, 12, 0, 0));

    $this->agent = Agent::factory()->create();
    $this->session = Session::factory()->create(['agent_id' => $this->agent->id]);
    $this->conversation = Conversation::factory()->create([
        'agent_id' => $this->agent->id,
        'chat_session_id' => $this->session->id,
        'source' => ConversationSource::Widget,
    ]);
});

function rifForm(Agent $agent, array $attributes = []): FormDefinition
{
    return FormDefinition::factory()->forAgent($agent)->create([
        'name' => 'Book a demo',
        'slug' => 'book-a-demo',
        'is_active' => true,
        ...$attributes,
    ]);
}

it('issues a pending form instance for the requested form', function (string $requested): void {
    $form = rifForm($this->agent, ['form_type' => FormType::Booking]);

    $outcome = app(RenderInlineFormAction::class)->execute($this->agent, $this->conversation, $requested);

    $submission = FormSubmission::query()->sole();
    expect($submission->status)->toBe(FormSubmissionStatus::Pending)
        ->and($submission->form_definition_id)->toBe($form->id)
        ->and($submission->agent_id)->toBe($this->agent->id)
        ->and($submission->chat_conversation_id)->toBe($this->conversation->id)
        ->and($submission->expires_at->toDateTimeString())->toBe('2026-06-15 18:00:00')
        ->and($outcome->alreadySubmittedFormName)->toBeNull()
        ->and($outcome->form->formInstanceId)->toBe($submission->external_id)
        ->and($outcome->form->definitionSlug)->toBe('book-a-demo')
        ->and($outcome->form->formType)->toBe(FormType::Booking);
})->with(['book-a-demo', 'BOOK A DEMO', 'book_a_demo']);

it('issues nothing for a form the session already submitted or a capability manages', function (Closure $arrange, ?string $alreadySubmitted): void {
    $requested = $arrange($this);

    $outcome = app(RenderInlineFormAction::class)->execute($this->agent, $this->conversation, $requested);

    expect($outcome->form)->toBeNull()
        ->and($outcome->alreadySubmittedFormName)->toBe($alreadySubmitted)
        ->and(FormSubmission::query()->where('chat_conversation_id', $this->conversation->id)->exists())->toBeFalse();
})->with([
    'submitted in another conversation of the session' => [function (object $test): string {
        $form = rifForm($test->agent);
        $earlier = Conversation::factory()->create([
            'agent_id' => $test->agent->id,
            'chat_session_id' => $test->session->id,
            'source' => ConversationSource::Widget,
        ]);
        FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $earlier->id]);

        return 'book-a-demo';
    }, 'Book a demo'],
    'managed by a capability' => [function (object $test): string {
        rifForm($test->agent, ['slug' => 'meeting-bookings', 'name' => 'Meeting bookings', 'managed_by' => 'calendar']);

        return 'meeting-bookings';
    }, null],
]);
