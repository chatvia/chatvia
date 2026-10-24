<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Models\EvalCase;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;

/**
 * "Revise answer" in the chat log saves the corrected answer as a Q&A entry
 * and, when asked, as an eval case judged against it. Adding the eval case
 * needs the eval permission; a question already in the cases adds none.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
    $conversation = Conversation::factory()->create(['agent_id' => $this->agent->id]);
    $this->reply = Message::factory()->fromAssistant()->create(['chat_conversation_id' => $conversation->id]);
    $this->payload = [
        'message_id' => (string) $this->reply->id,
        'user_message' => 'How long do refunds take?',
        'expected_response' => 'Refunds take five working days.',
    ];
});

it('saves only the Q&A entry by default', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.logs.revise-answer', $this->agent), $this->payload)
        ->assertOk()
        ->assertJson(['eval_case_created' => false]);

    expect(AgentQuestionAnswer::query()->where('agent_id', $this->agent->id)->count())->toBe(1)
        ->and(EvalCase::query()->forAgent($this->agent->id)->count())->toBe(0);
});

it('also adds an eval case judged against the corrected answer', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.logs.revise-answer', $this->agent), [...$this->payload, 'add_eval_case' => true])
        ->assertOk()
        ->assertJson(['eval_case_created' => true]);

    $case = EvalCase::query()->forAgent($this->agent->id)->sole();

    expect($case->input)->toBe('How long do refunds take?')
        ->and($case->expected_output)->toBe('Refunds take five working days.')
        ->and($case->scorer_type)->toBe(ScorerType::LlmJudge)
        ->and($case->is_active)->toBeTrue();
});

it('adds no second eval case for a question already in the cases', function (): void {
    EvalCase::query()->create([
        'agent_id' => $this->agent->id,
        'agent_name' => $this->agent->name,
        'workspace_id' => $this->agent->workspace_id,
        'name' => 'Refunds',
        'input' => 'how long do refunds take?',
        'scorer_type' => ScorerType::Contains->value,
        'is_active' => true,
    ]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.logs.revise-answer', $this->agent), [...$this->payload, 'add_eval_case' => true])
        ->assertOk()
        ->assertJson(['eval_case_created' => false]);

    expect(EvalCase::query()->forAgent($this->agent->id)->count())->toBe(1);
});

it('refuses to add an eval case for a teammate without the eval permission', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.sources.train.qa']);

    $this->actingAs($teammate)
        ->postJson(route('agents.logs.revise-answer', $this->agent), [...$this->payload, 'add_eval_case' => true])
        ->assertForbidden();

    expect(AgentQuestionAnswer::query()->where('agent_id', $this->agent->id)->count())->toBe(0);
});
