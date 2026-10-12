<?php

use Domain\Agent\Models\Agent;
use Domain\Agent\Notifications\AgentRateLimitNotification;
use Domain\Conversation\Events\AgentRateLimitExceeded;
use Domain\Conversation\Listeners\SendAgentRateLimitNotification;
use Illuminate\Support\Facades\Notification;

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
