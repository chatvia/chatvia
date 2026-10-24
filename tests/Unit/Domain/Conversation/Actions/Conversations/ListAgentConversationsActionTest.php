<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Domain\Conversation\Actions\Conversations\ListAgentConversationsAction;
use Domain\Conversation\Data\Conversations\ConversationSummaryData;
use Domain\Conversation\Models\Conversation;

/**
 * The API conversation list shows only the agent's visible conversations,
 * newest activity first, paged with a cursor.
 */
/** A visible conversation of the agent, as the list shows it. */
function lacaConversation(Agent $agent, array $attributes = []): Conversation
{
    return Conversation::factory()->forAgent($agent)->visible()->create($attributes);
}

it('lists only the agent\'s visible conversations, latest activity first', function (): void {
    $agent = Agent::factory()->create();
    $older = lacaConversation($agent, ['updated_at' => now()->subDay()]);
    $newer = lacaConversation($agent, ['updated_at' => now()]);
    lacaConversation($agent, ['is_visible' => false]);
    lacaConversation(Agent::factory()->create());

    $page = app(ListAgentConversationsAction::class)->execute($agent, perPage: 25);

    expect($page->data->map(fn (ConversationSummaryData $row): string => $row->id)->all())
        ->toBe([$newer->external_id, $older->external_id])
        ->and($page->next_cursor)->toBeNull();
});

it('walks the list page by page with the cursor, without skipping or repeating a row', function (): void {
    $agent = Agent::factory()->create();
    // Two rows share an updated_at, so the higher id comes first.
    $sameTime = now()->subHour()->startOfSecond();
    $newest = lacaConversation($agent, ['updated_at' => now()->subMinute()]);
    $tiedFirst = lacaConversation($agent, ['updated_at' => $sameTime]);
    $tiedSecond = lacaConversation($agent, ['updated_at' => $sameTime]);
    $dayOld = lacaConversation($agent, ['updated_at' => now()->subDay()]);
    $oldest = lacaConversation($agent, ['updated_at' => now()->subDays(2)]);

    $list = app(ListAgentConversationsAction::class);
    $first = $list->execute($agent, perPage: 2);
    $second = $list->execute($agent, perPage: 2, cursor: $first->next_cursor);
    $third = $list->execute($agent, perPage: 2, cursor: $second->next_cursor);

    $seen = collect([$first, $second, $third])
        ->flatMap(fn ($page) => $page->data->map(fn (ConversationSummaryData $row): string => $row->id))
        ->all();

    expect($first->data)->toHaveCount(2)
        ->and($first->next_cursor)->toBeString()
        ->and($third->data)->toHaveCount(1)
        ->and($third->next_cursor)->toBeNull()
        ->and($seen)->toBe([$newest->external_id, $tiedSecond->external_id, $tiedFirst->external_id, $dayOld->external_id, $oldest->external_id]);
});

it('starts again at the first page for a cursor it did not hand out', function (string $cursor): void {
    $agent = Agent::factory()->create();
    $newest = lacaConversation($agent, ['updated_at' => now()]);
    lacaConversation($agent, ['updated_at' => now()->subDay()]);

    $page = app(ListAgentConversationsAction::class)->execute($agent, perPage: 1, cursor: $cursor);

    expect($page->data->first()->id)->toBe($newest->external_id);
})->with([
    'garbage' => 'not-a-cursor',
    'missing id' => base64_encode(json_encode(['updated_at' => '2026-01-01 00:00:00', '_pointsToNextItems' => true])),
    'bad timestamp' => base64_encode(json_encode(['updated_at' => '2026-13-45 99:00:00', 'id' => 1, '_pointsToNextItems' => true])),
]);

it('shows sentiment and tone', function (): void {
    $agent = Agent::factory()->create();
    lacaConversation($agent, ['sentiment' => 'negative', 'tone' => 'frustrated']);

    $row = app(ListAgentConversationsAction::class)->execute($agent, perPage: 25)->data->first();

    expect($row->sentiment)->toBe(Sentiment::Negative)
        ->and($row->tone)->toBe(Tone::Frustrated);
});
