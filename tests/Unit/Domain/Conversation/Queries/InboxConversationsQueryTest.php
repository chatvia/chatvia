<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Queries\InboxConversationsQuery;

/**
 * The Inbox list: online, waiting and held chats of the given agents, waiting
 * first, then held by the operator, then by latest activity. Stale, hidden
 * and other agents' chats stay out.
 */
beforeEach(function (): void {
    $this->operator = User::factory()->create();
    $this->agent = Agent::factory()->create();
    $this->query = new InboxConversationsQuery;
});

it('lists online, waiting and held chats in that order, leaving out stale, hidden and other agents\' chats', function (): void {
    $online = Conversation::factory()->forAgent($this->agent)->live()->create(['last_activity_at' => now()->subMinute()]);
    $mine = Conversation::factory()->forAgent($this->agent)->live()->create(['handoff_status' => 'human', 'handoff_user_id' => $this->operator->id, 'last_activity_at' => now()->subHours(3)]);
    $waiting = Conversation::factory()->forAgent($this->agent)->live()->create(['handoff_status' => 'pending', 'last_activity_at' => now()->subHours(2)]);
    Conversation::factory()->forAgent($this->agent)->live()->create(['last_activity_at' => now()->subMinutes(10)]);
    Conversation::factory()->forAgent($this->agent)->live()->create(['is_visible' => false]);
    Conversation::factory()->forAgent($this->agent)->live()->create(['handoff_status' => 'pending', 'last_activity_at' => now()->subDays(2)]);
    Conversation::factory()->forAgent($this->agent)->live()->create(['handoff_status' => 'human', 'handoff_user_id' => User::factory()->create()->id, 'last_activity_at' => now()->subHour()]);
    Conversation::factory()->forAgent(Agent::factory()->create())->live()->create();

    $ids = $this->query->get(collect([$this->agent]), $this->operator)->pluck('id')->all();

    expect($ids)->toBe([$waiting->id, $mine->id, $online->id]);
});

it('counts the chats waiting for a human', function (): void {
    Conversation::factory()->forAgent($this->agent)->live()->create(['handoff_status' => 'pending']);
    Conversation::factory()->forAgent($this->agent)->live()->create(['handoff_status' => 'pending', 'last_activity_at' => now()->subDays(2)]);
    Conversation::factory()->forAgent($this->agent)->live()->create();

    expect($this->query->waitingCount(collect([$this->agent])))->toBe(1)
        ->and($this->query->waitingCount(collect()))->toBe(0);
});
