<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConfidenceBand;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Enums\MessageOrigin;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\QueryBuilders\ConversationQueryBuilder;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Support\Facades\DB;

/**
 * The chat-log filters: by topic (through the conversation_topics pivot, so
 * a multi-label conversation appears under each of its topics), by the
 * confidence band and the feedback of an assistant answer, by an answer that
 * still needs a look (unanswered), by online status
 * and by whole days. The latest message of each conversation loads in the
 * same query.
 */
it('matches conversations carrying the requested topic, multi-label aware', function (): void {
    $agent = Agent::factory()->create();
    $billing = Topic::factory()->create(['workspace_id' => $agent->workspace_id, 'topic' => 'Billing']);
    $bug = Topic::factory()->create(['workspace_id' => $agent->workspace_id, 'topic' => 'Bug']);

    $multi = cqbConversation($agent);
    $multi->topics()->attach([$billing->id => ['workspace_id' => $agent->workspace_id], $bug->id => ['workspace_id' => $agent->workspace_id]]);

    $single = cqbConversation($agent);
    $single->topics()->attach([$bug->id => ['workspace_id' => $agent->workspace_id]]);

    $billingMatches = Conversation::query()->filterByTopic((string) $billing->id)->pluck('id')->all();
    expect($billingMatches)->toContain($multi->id)
        ->and($billingMatches)->not->toContain($single->id);

    $bugMatches = Conversation::query()->filterByTopic((string) $bug->id)->pluck('id')->all();
    expect($bugMatches)->toContain($multi->id)
        ->and($bugMatches)->toContain($single->id);
});

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

    // The column keeps two decimals, so these are the scores right below and on each floor.
    foreach (['0.64', (string) ConfidenceBand::MEDIUM_FLOOR, '0.79', (string) ConfidenceBand::HIGH_FLOOR, '0.95'] as $score) {
        $conversation = cqbConversation($agent);
        Message::factory()->fromAssistant()->create(['chat_conversation_id' => $conversation->id, 'confidence_score' => (float) $score]);
        $byScore[$score] = $conversation->id;
    }

    // A visitor message never carries the answer's confidence.
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

it('ignores an unknown confidence band and an empty one', function (): void {
    $agent = Agent::factory()->create();
    $answered = cqbConversation($agent);
    Message::factory()->fromAssistant()->create(['chat_conversation_id' => $answered->id, 'confidence_score' => 0.9]);
    $unanswered = cqbConversation($agent);

    expect(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByConfidenceScore(null)))->toBe([$answered->id, $unanswered->id])
        // An unknown band still needs a scored assistant answer, at any score.
        ->and(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByConfidenceScore('sky-high')))->toBe([$answered->id]);
});

it('loads the latest message of each conversation through one query', function (): void {
    $agent = Agent::factory()->create();
    $chatty = cqbConversation($agent);
    $at = now()->subMinutes(5)->startOfSecond();
    Message::factory()->fromUser()->create(['chat_conversation_id' => $chatty->id, 'content' => 'first', 'created_at' => $at->copy()->subMinute()]);
    // Two in the same second: the later stored one is the latest.
    Message::factory()->fromUser()->create(['chat_conversation_id' => $chatty->id, 'content' => 'second', 'created_at' => $at]);
    $latest = Message::factory()->fromAssistant()->create(['chat_conversation_id' => $chatty->id, 'content' => 'third', 'created_at' => $at]);
    $silent = cqbConversation($agent);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $conversations = Conversation::query()->forAgent($agent->id)->withLastMessage()->orderBy('id')->get()->keyBy('id');

    $last = $conversations[$chatty->id]->lastMessage;
    expect($queries)->toBe(1)
        ->and($last)->toBeInstanceOf(Message::class)
        ->and($last->id)->toBe($latest->id)
        ->and($last->content)->toBe('third')
        ->and($last->role)->toBe(MessageRole::Assistant)
        ->and($last->created_at->equalTo($at))->toBeTrue()
        ->and($conversations[$silent->id]->lastMessage)->toBeNull()
        ->and($conversations[$chatty->id]->getAttributes())->not->toHaveKey('last_message_content')
        ->and($conversations[$chatty->id]->isDirty())->toBeFalse()
        ->and($conversations[$chatty->id]->agent_id)->toBe($agent->id);
});

it('filters by the feedback on an assistant answer only', function (): void {
    $agent = Agent::factory()->create();
    $disliked = cqbConversation($agent);
    Message::factory()->fromAssistant()->withNegativeFeedback()->create(['chat_conversation_id' => $disliked->id]);
    $liked = cqbConversation($agent);
    Message::factory()->fromAssistant()->withPositiveFeedback()->create(['chat_conversation_id' => $liked->id]);
    $onVisitorMessage = cqbConversation($agent);
    Message::factory()->fromUser()->withNegativeFeedback()->create(['chat_conversation_id' => $onVisitorMessage->id]);

    expect(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByFeedback('negative')))->toBe([$disliked->id])
        ->and(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByFeedback('positive')))->toBe([$liked->id])
        ->and(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByFeedback(null)))->toBe([$disliked->id, $liked->id, $onVisitorMessage->id]);
});

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
    // A Q&A match counts as found even when no passage was given.
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

it('keeps only the conversations active within the online window when asked', function (): void {
    $this->freezeSecond();
    $agent = Agent::factory()->create();
    $window = ConversationQueryBuilder::ONLINE_WINDOW_MINUTES;
    $justNow = cqbConversation($agent, ['last_activity_at' => now()->subMinute()]);
    $onTheEdge = cqbConversation($agent, ['last_activity_at' => now()->subMinutes($window)]);
    $gone = cqbConversation($agent, ['last_activity_at' => now()->subMinutes($window)->subSecond()]);
    $never = cqbConversation($agent, ['last_activity_at' => null]);

    expect(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByOnlineStatus(true)))->toBe([$justNow->id, $onTheEdge->id])
        ->and(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByOnlineStatus(false)))->toBe([$justNow->id, $onTheEdge->id, $gone->id, $never->id]);
});

it('filters by whole days from the start date to the end date, both included', function (): void {
    $agent = Agent::factory()->create();
    $dayBefore = cqbConversation($agent, ['created_at' => '2026-02-28 23:59:59']);
    $firstDay = cqbConversation($agent, ['created_at' => '2026-03-01 00:00:00']);
    $lastDay = cqbConversation($agent, ['created_at' => '2026-03-31 23:59:59']);
    $dayAfter = cqbConversation($agent, ['created_at' => '2026-04-01 00:00:00']);

    expect(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByDateRange('2026-03-01', '2026-03-31')))->toBe([$firstDay->id, $lastDay->id])
        // Both ends are needed; one alone filters nothing.
        ->and(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByDateRange('2026-03-01', null)))->toBe([$dayBefore->id, $firstDay->id, $lastDay->id, $dayAfter->id]);
});
