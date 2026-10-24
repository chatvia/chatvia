<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Queries\PendingHandoffsQuery;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * The dashboard's list of chats waiting for a teammate: the visible pending
 * conversations of one agent, most recently updated first, up to its limit,
 * each with its latest message.
 */
it('lists only the visible pending conversations of the agent', function (): void {
    $agent = Agent::factory()->create();
    $pending = Conversation::factory()->count(2)->forAgent($agent)->visible()->handedOff(HandoffStatus::Pending)->create();

    Conversation::factory()->forAgent($agent)->visible()->handedOff(HandoffStatus::Human, $agent->workspace->owner)->create();
    Conversation::factory()->forAgent($agent)->visible()->create();
    Conversation::factory()->forAgent(Agent::factory()->create())->visible()->handedOff(HandoffStatus::Pending)->create();
    Conversation::factory()->forAgent($agent)->handedOff(HandoffStatus::Pending)->create(['is_visible' => false]);

    expect(app(PendingHandoffsQuery::class)->get($agent)->pluck('id')->all())
        ->toEqualCanonicalizing($pending->pluck('id')->all());
});

it('lists the most recently updated conversation first', function (): void {
    $this->freezeSecond();
    $agent = Agent::factory()->create();
    $pending = fn (int $minutesAgo): Conversation => Conversation::factory()->forAgent($agent)->visible()->handedOff(HandoffStatus::Pending)
        ->create(['updated_at' => now()->subMinutes($minutesAgo)]);

    $older = $pending(30);
    $newest = $pending(1);
    $middle = $pending(10);

    expect(app(PendingHandoffsQuery::class)->get($agent)->pluck('id')->all())
        ->toBe([$newest->id, $middle->id, $older->id]);
});

it('lists at most its limit and leaves out the oldest', function (): void {
    $this->freezeSecond();
    $agent = Agent::factory()->create();

    $conversations = Conversation::factory()
        ->count(PendingHandoffsQuery::LIMIT + 1)
        ->forAgent($agent)
        ->visible()
        ->handedOff(HandoffStatus::Pending)
        ->sequence(fn (Sequence $sequence): array => ['updated_at' => now()->subMinutes($sequence->index)])
        ->create();

    $rows = app(PendingHandoffsQuery::class)->get($agent);

    expect($rows)->toHaveCount(PendingHandoffsQuery::LIMIT)
        ->and($rows->pluck('id'))->not->toContain($conversations->last()->id);
});

it('carries the latest message of each conversation', function (): void {
    $agent = Agent::factory()->create();
    $waiting = Conversation::factory()->forAgent($agent)->visible()->handedOff(HandoffStatus::Pending)->create();
    $quiet = Conversation::factory()->forAgent($agent)->visible()->handedOff(HandoffStatus::Pending)->create();
    Message::factory()->fromUser()->create(['chat_conversation_id' => $waiting->id, 'content' => 'Hello?', 'created_at' => now()->subMinutes(2)]);
    Message::factory()->fromUser()->create(['chat_conversation_id' => $waiting->id, 'content' => 'Anyone there?', 'created_at' => now()->subMinute()]);

    $rows = app(PendingHandoffsQuery::class)->get($agent)->keyBy('id');

    expect($rows[$waiting->id]->lastMessage?->content)->toBe('Anyone there?')
        ->and($rows[$quiet->id]->lastMessage)->toBeNull();
});
