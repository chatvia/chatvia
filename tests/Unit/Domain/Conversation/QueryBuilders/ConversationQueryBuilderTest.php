<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConfidenceBand;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Enums\MessageOrigin;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\QueryBuilders\ConversationQueryBuilder;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;

/**
 * The chat-log filters with real branches: the confidence band of an
 * assistant answer, on the band floors, and an answer that still needs a
 * look (no knowledge found and not revised, or also a thumbs-down).
 */
/** A widget conversation of the agent; the attributes override the defaults. */
function cqbConversation(Agent $agent, array $attributes = []): Conversation
{
    return Conversation::factory()->forAgent($agent)->create($attributes);
}

/** @return list<int> the ids of the agent's conversations the query keeps */
function cqbIds(Agent $agent, Closure $filter): array
{
    return $filter(Conversation::query()->forAgent($agent->id))->orderBy('id')->pluck('id')->all();
}

it('filters by the confidence band of an assistant answer, on the band floors', function (string $band, array $kept, array $dropped): void {
    $agent = Agent::factory()->create();
    $byScore = [];

    foreach (['0.64', (string) ConfidenceBand::MEDIUM_FLOOR, '0.79', (string) ConfidenceBand::HIGH_FLOOR, '0.95'] as $score) {
        $conversation = cqbConversation($agent);
        Message::factory()->fromAssistant()->create(['chat_conversation_id' => $conversation->id, 'confidence_score' => (float) $score]);
        $byScore[$score] = $conversation->id;
    }

    $visitorOnly = cqbConversation($agent);
    Message::factory()->fromUser()->create(['chat_conversation_id' => $visitorOnly->id, 'confidence_score' => 0.3]);

    $ids = cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByConfidenceScore($band));
    $idsOf = fn (array $scores): array => array_map(fn (string $score): int => $byScore[$score], $scores);

    expect($ids)->toContain(...$idsOf($kept))
        ->and($ids)->not->toContain(...$idsOf($dropped), ...[$visitorOnly->id]);
})->with([
    'low: below the medium floor' => ['low', ['0.64'], ['0.65', '0.79', '0.8', '0.95']],
    'medium: from the medium floor up to, not including, the high floor' => ['medium', ['0.65', '0.79'], ['0.64', '0.8', '0.95']],
    'high: from the high floor up' => ['high', ['0.8', '0.95'], ['0.64', '0.65', '0.79']],
]);

it('filters by an unrevised reply with no knowledge found, or also a thumbs-down', function (): void {
    $agent = Agent::factory()->create();
    $reply = fn (Conversation $conversation, array $attributes = []): Message => Message::factory()->fromAssistant()
        ->create(['chat_conversation_id' => $conversation->id, 'has_llm_found_answer' => true, ...$attributes]);

    $notFound = cqbConversation($agent);
    $reply($notFound, ['has_llm_found_answer' => false]);
    $disliked = cqbConversation($agent);
    $reply($disliked, ['feedback' => MessageFeedback::Negative]);
    $answered = cqbConversation($agent);
    $reply($answered);
    $fromQa = cqbConversation($agent);
    $reply($fromQa, ['has_llm_found_answer' => false, 'is_response_from_qa' => true]);
    $revised = cqbConversation($agent);
    $qa = AgentQuestionAnswer::query()->create(['agent_id' => $agent->id, 'title' => 'Fixed', 'questions' => 'Fixed', 'answer' => 'Yes.']);
    $reply($revised, ['has_llm_found_answer' => false, 'agent_question_answer_id' => $qa->id]);
    $playground = cqbConversation($agent);
    $reply($playground, ['has_llm_found_answer' => false, 'source' => MessageOrigin::Playground]);

    expect(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByUnanswered('not_found')))->toBe([$notFound->id])
        ->and(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByUnanswered('needs_look')))->toBe([$notFound->id, $disliked->id])
        ->and(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByUnanswered('everything')))->toHaveCount(6)
        ->and(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByUnanswered(null)))->toHaveCount(6);
});
