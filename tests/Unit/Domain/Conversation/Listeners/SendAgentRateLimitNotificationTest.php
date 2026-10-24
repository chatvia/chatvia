<?php

use Domain\Agent\Models\Agent;
use Domain\Agent\Notifications\AgentRateLimitNotification;
use Domain\Conversation\Events\AgentRateLimitExceeded;
use Domain\Conversation\Listeners\SendAgentRateLimitNotification;
use Illuminate\Support\Facades\Notification;

/**
 * An agent over its per-minute cap emails the workspace owner, at most once
 * an hour for each agent.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
});

it('emails the workspace owner when the agent trips its rate limit', function (): void {
    Notification::fake();

    (new SendAgentRateLimitNotification)->handle(
        new AgentRateLimitExceeded($this->agent, 20),
    );

    Notification::assertSentTo($this->agent->workspace->owner, AgentRateLimitNotification::class);
});

it('throttles to a single email per hour per agent', function (): void {
    Notification::fake();

    $listener = new SendAgentRateLimitNotification;
    $event = new AgentRateLimitExceeded($this->agent, 20);

    $listener->handle($event);
    $listener->handle($event);
    $listener->handle($event);

    Notification::assertSentToTimes($this->agent->workspace->owner, AgentRateLimitNotification::class, 1);
});

it('throttles each agent on its own, so another agent of the owner still emails', function (): void {
    Notification::fake();
    $owner = $this->agent->workspace->owner;
    $second = createAgent($owner);

    $listener = new SendAgentRateLimitNotification;
    $listener->handle(new AgentRateLimitExceeded($this->agent, 20));
    $listener->handle(new AgentRateLimitExceeded($this->agent, 20));
    $listener->handle(new AgentRateLimitExceeded($second, 20));

    Notification::assertSentToTimes($owner, AgentRateLimitNotification::class, 2);
    Notification::assertSentTo($owner, AgentRateLimitNotification::class, fn (AgentRateLimitNotification $notification): bool => $notification->agent->is($second));
});

it('emails again once the hour has passed', function (): void {
    Notification::fake();
    $this->freezeTime();

    $listener = new SendAgentRateLimitNotification;
    $event = new AgentRateLimitExceeded($this->agent, 20);

    $listener->handle($event);
    $this->travel(59)->minutes();
    $listener->handle($event);
    $this->travel(2)->minutes();
    $listener->handle($event);

    Notification::assertSentToTimes($this->agent->workspace->owner, AgentRateLimitNotification::class, 2);
});
