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
 * Rendering a form resolves the model's request against the agent's REAL
 * active forms only, refuses a form this visitor's session already submitted,
 * and otherwise issues a pending form instance the visitor submits into.
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

it('issues a pending form instance for the requested form', function (): void {
    $form = rifForm($this->agent, ['form_type' => FormType::Booking, 'subtitle' => 'Pick a time.', 'success_message' => 'See you soon.']);

    $outcome = app(RenderInlineFormAction::class)->execute($this->agent, $this->conversation, 'book-a-demo');

    $submission = FormSubmission::query()->sole();
    expect($submission->status)->toBe(FormSubmissionStatus::Pending)
        ->and($submission->form_definition_id)->toBe($form->id)
        ->and($submission->agent_id)->toBe($this->agent->id)
        ->and($submission->chat_conversation_id)->toBe($this->conversation->id)
        ->and($submission->expires_at->toDateTimeString())->toBe('2026-06-15 18:00:00')
        ->and($submission->ip_address)->toBeNull()
        ->and($submission->user_agent)->toBeNull();

    $rendered = $outcome->form;
    expect($outcome->alreadySubmittedFormName)->toBeNull()
        ->and($rendered->formInstanceId)->toBe($submission->external_id)
        ->and($rendered->definitionSlug)->toBe('book-a-demo')
        ->and($rendered->formType)->toBe(FormType::Booking)
        ->and($rendered->title)->toBe('Book a demo')
        ->and($rendered->subtitle)->toBe('Pick a time.')
        ->and($rendered->schema)->not->toBeEmpty()
        ->and($rendered->submitLabel)->toBe('Submit')
        ->and($rendered->successMessage)->toBe('See you soon.')
        ->and($rendered->status)->toBe(FormSubmissionStatus::Pending)
        ->and($rendered->expiresAt->toIso8601String())->toBe('2026-06-15T18:00:00+00:00');
});

it('falls back to the default success message', function (): void {
    rifForm($this->agent, ['success_message' => null]);

    $outcome = app(RenderInlineFormAction::class)->execute($this->agent, $this->conversation, 'book-a-demo');

    expect($outcome->form->successMessage)->toBe('Thanks — your details have been received.');
});

it('matches a form by its display name or a snake_case slug', function (string $requested): void {
    rifForm($this->agent);

    $outcome = app(RenderInlineFormAction::class)->execute($this->agent, $this->conversation, $requested);

    expect($outcome->form?->definitionSlug)->toBe('book-a-demo');
})->with(['Book a demo', 'BOOK A DEMO', 'book_a_demo']);

it('lists the active slugs, oldest first, when nothing matches', function (): void {
    rifForm($this->agent);
    rifForm($this->agent, ['name' => 'Get a quote', 'slug' => 'get-a-quote']);
    rifForm($this->agent, ['name' => 'Retired', 'slug' => 'retired', 'is_active' => false]);
    rifForm(Agent::factory()->create(), ['name' => 'Newsletter', 'slug' => 'newsletter']);

    $outcome = app(RenderInlineFormAction::class)->execute($this->agent, $this->conversation, 'newsletter');

    expect($outcome->form)->toBeNull()
        ->and($outcome->alreadySubmittedFormName)->toBeNull()
        ->and($outcome->activeSlugs)->toBe(['book-a-demo', 'get-a-quote'])
        ->and(FormSubmission::query()->exists())->toBeFalse();
});

it('names at most eight active slugs', function (): void {
    foreach (range(1, 10) as $i) {
        rifForm($this->agent, ['name' => "Form {$i}", 'slug' => "form-{$i}"]);
    }

    $outcome = app(RenderInlineFormAction::class)->execute($this->agent, $this->conversation, 'missing');

    expect($outcome->activeSlugs)->toHaveCount(8)->toContain('form-1');
});

it('reports no active slugs when the agent has no active form', function (): void {
    rifForm($this->agent, ['is_active' => false]);

    $outcome = app(RenderInlineFormAction::class)->execute($this->agent, $this->conversation, 'book-a-demo');

    expect($outcome->form)->toBeNull()
        ->and($outcome->activeSlugs)->toBe([]);
});

it('refuses a form the session already submitted in another conversation', function (): void {
    $form = rifForm($this->agent);
    $earlier = Conversation::factory()->create([
        'agent_id' => $this->agent->id,
        'chat_session_id' => $this->session->id,
        'source' => ConversationSource::Widget,
    ]);
    FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $earlier->id]);

    $outcome = app(RenderInlineFormAction::class)->execute($this->agent, $this->conversation, 'book-a-demo');

    expect($outcome->form)->toBeNull()
        ->and($outcome->alreadySubmittedFormName)->toBe('Book a demo')
        ->and(FormSubmission::query()->where('chat_conversation_id', $this->conversation->id)->exists())->toBeFalse();
});
