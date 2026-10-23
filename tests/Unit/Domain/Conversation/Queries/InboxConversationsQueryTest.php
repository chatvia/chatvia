<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Queries\InboxConversationsQuery;

/**
 * The Inbox list: online, waiting and held chats of the given agents, waiting
 * first, then held by the operator, then by latest activity. Stale, hidden
 * and other agents' chats stay out.
 */
function iqcChat(Agent $agent, array $attributes = []): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    return Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::Widget,
        'is_visible' => true,
        'last_activity_at' => now(),
        ...$attributes,
    ]);
}

beforeEach(function (): void {
    $this->operator = User::factory()->create();
    $this->agent = Agent::factory()->create();
    $this->query = new InboxConversationsQuery;
});

it('lists online, waiting and held chats in that order', function (): void {
    $online = iqcChat($this->agent, ['last_activity_at' => now()->subMinute()]);
    $mine = iqcChat($this->agent, ['handoff_status' => 'human', 'handoff_user_id' => $this->operator->id, 'last_activity_at' => now()->subHours(3)]);
    $waiting = iqcChat($this->agent, ['handoff_status' => 'pending', 'last_activity_at' => now()->subHours(2)]);

    $ids = $this->query->get(collect([$this->agent]), $this->operator)->pluck('id')->all();

    expect($ids)->toBe([$waiting->id, $mine->id, $online->id]);
});

it('leaves out stale, hidden and other agents\' chats', function (): void {
    iqcChat($this->agent, ['last_activity_at' => now()->subMinutes(10)]);
    iqcChat($this->agent, ['is_visible' => false]);
    iqcChat($this->agent, ['handoff_status' => 'pending', 'last_activity_at' => now()->subDays(2)]);
    iqcChat($this->agent, ['handoff_status' => 'human', 'handoff_user_id' => User::factory()->create()->id, 'last_activity_at' => now()->subHour()]);
    iqcChat(Agent::factory()->create());

    expect($this->query->get(collect([$this->agent]), $this->operator))->toBeEmpty();
});

it('counts the chats waiting for a human', function (): void {
    iqcChat($this->agent, ['handoff_status' => 'pending']);
    iqcChat($this->agent, ['handoff_status' => 'pending', 'last_activity_at' => now()->subDays(2)]);
    iqcChat($this->agent);

    expect($this->query->waitingCount(collect([$this->agent])))->toBe(1)
        ->and($this->query->waitingCount(collect()))->toBe(0);
});

it('returns nothing without agents', function (): void {
    iqcChat($this->agent);

    expect($this->query->get(collect(), $this->operator))->toBeEmpty();
});
