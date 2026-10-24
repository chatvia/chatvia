<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Knowledge\Source\Qa\Actions\CreateQaFromMessageReviseAction;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Revising a reply in the chat log turns the owner's correction into a curated
 * Q&A of the agent and marks the reply as revised. Only an assistant reply of
 * this agent can be revised; anything else fails and writes nothing.
 */
function cqmrReply(Agent $agent, string $role = 'assistant'): Message
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id]);

    return Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => $role]);
}

it('creates a Q&A from the correction and links the reply to it', function (): void {
    $agent = Agent::factory()->create();
    $reply = cqmrReply($agent);

    $qa = app(CreateQaFromMessageReviseAction::class)->execute(
        $agent,
        (string) $reply->id,
        'How do I reset my password?',
        'Open Settings, then Security, then choose Reset password.',
    );

    expect($qa->agent_id)->toBe($agent->id)
        ->and($qa->title)->toBe('How do I reset my password?')
        ->and($qa->questions)->toBe('How do I reset my password?')
        ->and($qa->answer)->toBe('Open Settings, then Security, then choose Reset password.')
        ->and($reply->fresh()->agent_question_answer_id)->toBe($qa->id);
});

it('cuts the title to the first hundred characters of the question', function (): void {
    $agent = Agent::factory()->create();
    $reply = cqmrReply($agent);
    $question = str_repeat('é', 150);

    $qa = app(CreateQaFromMessageReviseAction::class)->execute($agent, (string) $reply->id, $question, 'Answer');

    expect(mb_strlen($qa->title))->toBe(100)
        ->and($qa->questions)->toBe($question);
});

it('refuses a reply of another agent and writes nothing', function (): void {
    $agent = Agent::factory()->create();
    $foreignReply = cqmrReply(Agent::factory()->create());

    expect(fn () => app(CreateQaFromMessageReviseAction::class)->execute($agent, (string) $foreignReply->id, 'Q', 'A'))
        ->toThrow(ModelNotFoundException::class);

    expect(AgentQuestionAnswer::query()->where('agent_id', $agent->id)->exists())->toBeFalse()
        ->and($foreignReply->fresh()->agent_question_answer_id)->toBeNull();
});

it('refuses a visitor message', function (): void {
    $agent = Agent::factory()->create();
    $visitorMessage = cqmrReply($agent, role: 'user');

    expect(fn () => app(CreateQaFromMessageReviseAction::class)->execute($agent, (string) $visitorMessage->id, 'Q', 'A'))
        ->toThrow(ModelNotFoundException::class);

    expect(AgentQuestionAnswer::query()->where('agent_id', $agent->id)->exists())->toBeFalse();
});
