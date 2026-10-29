<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Events\AgentRateLimitExceeded;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * The agent's messages-per-minute cap and each visitor's own cap, in fixed
 * one-minute windows that refused turns do not extend. A cap of zero is off.
 * Going over the agent's cap alerts the owner who opted in.
 */
beforeEach(function (): void {
    Cache::flush();
    $this->limiter = app(AgentChatRateLimiter::class);
});

it('blocks over the cap in a fixed window that refused turns do not extend, and never with a cap of zero', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');
    $uncapped = Agent::factory()->create();
    $uncapped->setSetting(key: 'chat_rate_limit_per_minute', value: 0, group: 'security');

    expect($this->limiter->exceeded($agent))->toBeFalse();
    $this->travel(30)->seconds();
    expect($this->limiter->exceeded($agent))->toBeTrue();
    $this->travel(31)->seconds();

    expect($this->limiter->exceeded($agent))->toBeFalse()
        ->and($this->limiter->exceeded($uncapped))->toBeFalse()
        ->and($this->limiter->exceeded($uncapped))->toBeFalse();
});

it('caps each visitor on their own, without counting toward the agent cap', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'visitor_rate_limit_per_minute', value: 2, group: 'security');
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 3, group: 'security');

    expect($this->limiter->visitorExceeded($agent, 'session-a'))->toBeFalse()
        ->and($this->limiter->visitorExceeded($agent, 'session-a'))->toBeFalse()
        ->and($this->limiter->visitorExceeded($agent, 'session-a'))->toBeTrue()
        ->and($this->limiter->visitorExceeded($agent, 'session-b'))->toBeFalse()
        ->and($this->limiter->exceeded($agent))->toBeFalse();
});

it('alerts once over the cap only when the owner opted in', function (): void {
    Event::fake([AgentRateLimitExceeded::class]);
    $optedIn = Agent::factory()->create();
    $optedIn->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');
    $optedIn->setSetting(key: 'notify_owner_on_rate_limit', value: true, group: 'security');
    $optedOut = Agent::factory()->create();
    $optedOut->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');

    foreach ([$optedIn, $optedOut] as $agent) {
        $this->limiter->exceeded($agent);
        $this->limiter->exceeded($agent);
    }

    Event::assertDispatchedTimes(AgentRateLimitExceeded::class, 1);
    Event::assertDispatched(AgentRateLimitExceeded::class, fn (AgentRateLimitExceeded $e): bool => $e->agent->is($optedIn) && $e->limitPerMinute === 1);
});
