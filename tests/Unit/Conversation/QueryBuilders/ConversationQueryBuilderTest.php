<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConfidenceBand;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\QueryBuilders\ConversationQueryBuilder;
use Domain\Conversation\Topic\Models\Topic;

/**
 * The chat-log filters. filterByTopic matches through the conversation_topics
 * pivot, so a multi-label conversation appears under each of its topics.
 */
it('matches conversations carrying the requested topic, multi-label aware', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    $billing = Topic::create(['workspace_id' => $agent->workspace_id, 'topic' => 'Billing', 'description' => 'x']);
    $bug = Topic::create(['workspace_id' => $agent->workspace_id, 'topic' => 'Bug', 'description' => 'y']);

    $multi = Conversation::factory()->create(['chat_session_id' => $session->id, 'source' => ConversationSource::Widget]);
    $multi->topics()->attach([$billing->id, $bug->id]);

    $single = Conversation::factory()->create(['chat_session_id' => $session->id, 'source' => ConversationSource::Widget]);
    $single->topics()->attach([$bug->id]);

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
    return Conversation::factory()->create([
        'chat_session_id' => Session::factory()->create(['agent_id' => $agent->id])->id,
        'source' => ConversationSource::Widget,
        ...$attributes,
    ]);
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
        // An unknown band still needs an assistant answer, at any score.
        ->and(cqbIds($agent, fn (ConversationQueryBuilder $query): ConversationQueryBuilder => $query->filterByConfidenceScore('sky-high')))->toBe([$answered->id]);
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
