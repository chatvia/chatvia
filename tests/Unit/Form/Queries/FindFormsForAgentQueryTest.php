<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Form\Data\FormMatchData;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Form\Queries\FindFormsForAgentQuery;

/**
 * Form discovery returns the agent's matching forms as DTOs, flagging the ones
 * this visitor's session already submitted.
 */
pest()->beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->demo = FormDefinition::factory()->forAgent($this->agent)->create([
        'name' => 'Book a demo', 'slug' => 'book-a-demo', 'is_active' => true,
        'agent_instructions' => 'When the visitor wants a demo.',
    ]);
    FormDefinition::factory()->forAgent($this->agent)->create([
        'name' => 'Get a quote', 'slug' => 'get-a-quote', 'is_active' => true,
    ]);
});

it('returns every matching form, flagging the ones the session submitted', function (): void {
    $session = Session::factory()->create(['agent_id' => $this->agent->id]);
    $conversation = Conversation::factory()->create([
        'agent_id' => $this->agent->id, 'chat_session_id' => $session->id, 'source' => ConversationSource::Widget,
    ]);
    FormSubmission::factory()->forDefinition($this->demo)->submitted()->create(['chat_conversation_id' => $conversation->id]);

    $matches = app(FindFormsForAgentQuery::class)->get($this->agent, $conversation, 'book a demo');

    $bySlug = collect($matches)->keyBy('slug');
    expect($bySlug->keys()->sort()->values()->all())->toBe(['book-a-demo', 'get-a-quote'])
        ->and($bySlug['book-a-demo']->name)->toBe('Book a demo')
        ->and($bySlug['book-a-demo']->purpose)->toBe('When the visitor wants a demo.')
        ->and($bySlug['book-a-demo']->alreadySubmitted)->toBeTrue()
        ->and($bySlug['get-a-quote']->alreadySubmitted)->toBeFalse();
});

it('flags nothing without a conversation', function (): void {
    FormSubmission::factory()->forDefinition($this->demo)->submitted()->create();

    $matches = app(FindFormsForAgentQuery::class)->get($this->agent, null, 'book a demo');

    expect($matches)->not->toBeEmpty()
        ->and(array_filter($matches, fn (FormMatchData $match): bool => $match->alreadySubmitted))->toBe([]);
});

it('returns nothing for an agent without active forms', function (): void {
    expect(app(FindFormsForAgentQuery::class)->get(Agent::factory()->create(), null, 'book a demo'))->toBe([]);
});
