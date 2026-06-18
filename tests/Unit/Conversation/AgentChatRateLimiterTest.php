<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Events\AgentRateLimitExceeded;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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

    foreach (range(1, 5) as $_) {
        expect($this->limiter->exceeded($agent))->toBeFalse();
    }
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

it('caches the security config so later turns do not re-query model_settings', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 5, group: 'security');

    // First turn warms the cache.
    $this->limiter->exceeded($agent);

    DB::enableQueryLog();

    // A freshly loaded instance simulates a subsequent request — the per-instance
    // settings cache is cold, so only the cross-request cache spares the SELECT.
    $this->limiter->exceeded(Agent::find($agent->id));

    $settingsReads = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_contains($q['query'], 'model_settings'));

    expect($settingsReads)->toBeEmpty();

    DB::disableQueryLog();
});

it('forget() drops the cached config so a new cap applies immediately', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');

    $this->limiter->exceeded($agent);                       // caches cap=1, 1 hit
    expect($this->limiter->exceeded($agent))->toBeTrue();   // over the cap of 1

    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 10, group: 'security');
    $this->limiter->forget($agent);

    // Without forget() the stale cap=1 would still block; the raised cap now wins.
    expect($this->limiter->exceeded($agent))->toBeFalse();
});
