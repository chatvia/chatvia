<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Events\AgentRateLimitExceeded;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * The agent's messages-per-minute cap and each visitor's own cap, in fixed
 * one-minute windows that refused turns do not extend. A cap of zero is off.
 * Going over the agent's cap alerts the owner who opted in. The agent's caps
 * are read once and kept until forget() drops them.
 */
beforeEach(function (): void {
    Cache::flush();
    $this->limiter = app(AgentChatRateLimiter::class);
});

it('allows turns up to the cap then blocks', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 2, group: 'security');

    expect($this->limiter->exceeded($agent))->toBeFalse()
        ->and($this->limiter->exceeded($agent))->toBeFalse()
        ->and($this->limiter->exceeded($agent))->toBeTrue();
});

it('treats a limit of zero as disabled', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 0, group: 'security');

    expect($this->limiter->exceeded($agent))->toBeFalse()
        ->and($this->limiter->exceeded($agent))->toBeFalse()
        ->and($this->limiter->exceeded($agent))->toBeFalse();
});

it('dispatches the alert event once over the cap when the owner opted in', function (): void {
    Event::fake([AgentRateLimitExceeded::class]);

    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');
    $agent->setSetting(key: 'notify_owner_on_rate_limit', value: true, group: 'security');

    $this->limiter->exceeded($agent); // fills the bucket
    $this->limiter->exceeded($agent); // over the cap

    Event::assertDispatched(
        AgentRateLimitExceeded::class,
        fn (AgentRateLimitExceeded $e): bool => $e->agent->is($agent) && $e->limitPerMinute === 1,
    );
});

it('does not dispatch the alert event when the owner opted out', function (): void {
    Event::fake([AgentRateLimitExceeded::class]);

    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');

    $this->limiter->exceeded($agent);
    $this->limiter->exceeded($agent);

    Event::assertNotDispatched(AgentRateLimitExceeded::class);
});

it('keeps the cap it read until forget() drops it, so a new cap applies at once', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');

    $this->limiter->exceeded($agent);                       // reads cap=1, 1 hit
    expect($this->limiter->exceeded($agent))->toBeTrue();   // over the cap of 1

    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 10, group: 'security');

    // A freshly loaded agent, as on the next request: the kept cap still applies.
    expect($this->limiter->exceeded(Agent::find($agent->id)))->toBeTrue();

    $this->limiter->forget($agent);

    expect($this->limiter->exceeded(Agent::find($agent->id)))->toBeFalse();
});

it('counts in a fixed window that refused turns do not extend', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');

    expect($this->limiter->exceeded($agent))->toBeFalse();   // opens the window

    $this->travel(30)->seconds();
    expect($this->limiter->exceeded($agent))->toBeTrue();    // refused, still counted

    // 61s after the FIRST message the window is over, even though a turn was
    // refused 31s ago — a sliding window would still block here.
    $this->travel(31)->seconds();
    expect($this->limiter->exceeded($agent))->toBeFalse();
});

it('caps each visitor on their own, without counting toward the agent cap', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'visitor_rate_limit_per_minute', value: 2, group: 'security');
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 3, group: 'security');

    expect($this->limiter->visitorExceeded($agent, 'session-a'))->toBeFalse()
        ->and($this->limiter->visitorExceeded($agent, 'session-a'))->toBeFalse()
        ->and($this->limiter->visitorExceeded($agent, 'session-a'))->toBeTrue()
        // Another visitor still gets through.
        ->and($this->limiter->visitorExceeded($agent, 'session-b'))->toBeFalse()
        // The refused visitor messages never reached the agent bucket.
        ->and($this->limiter->exceeded($agent))->toBeFalse();
});

it('does not cap a turn without a visitor', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'visitor_rate_limit_per_minute', value: 1, group: 'security');

    expect($this->limiter->visitorExceeded($agent, ''))->toBeFalse()
        ->and($this->limiter->visitorExceeded($agent, ''))->toBeFalse();
});
