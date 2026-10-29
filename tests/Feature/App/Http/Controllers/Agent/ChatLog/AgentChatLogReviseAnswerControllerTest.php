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
 * needs the eval permission.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
    $reply = Message::factory()->fromAssistant()->create(['chat_conversation_id' => Conversation::factory()->create(['agent_id' => $this->agent->id])->id]);
    $this->payload = [
        'message_id' => (string) $reply->id,
        'user_message' => 'How long do refunds take?',
        'expected_response' => 'Refunds take five working days.',
        'add_eval_case' => true,
    ];
});

it('saves the Q&A entry and an eval case judged against the corrected answer', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.logs.revise-answer', $this->agent), $this->payload)
        ->assertOk()
        ->assertJson(['eval_case_created' => true]);

    $case = EvalCase::query()->forAgent($this->agent->id)->sole();
    expect(AgentQuestionAnswer::query()->where('agent_id', $this->agent->id)->count())->toBe(1)
        ->and($case->input)->toBe('How long do refunds take?')
        ->and($case->expected_output)->toBe('Refunds take five working days.')
        ->and($case->scorer_type)->toBe(ScorerType::LlmJudge)
        ->and($case->is_active)->toBeTrue();
});

it('refuses to add an eval case for a teammate without the eval permission and saves nothing', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.sources.train.qa']);

    $this->actingAs($teammate)
        ->postJson(route('agents.logs.revise-answer', $this->agent), $this->payload)
        ->assertForbidden();

    expect(AgentQuestionAnswer::query()->where('agent_id', $this->agent->id)->exists())->toBeFalse()
        ->and(EvalCase::query()->forAgent($this->agent->id)->exists())->toBeFalse();
});
