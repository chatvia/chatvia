<?php

use App\Http\Chat\HostedChatAgent;
use Domain\Agent\Models\Agent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The hosted chat's LLM-cost limits: the caller's own cap, plus a ceiling per
 * server-resolved agent, so many sessions or IPs cannot drain one public
 * agent's monthly message limit.
 */
function rlspKeys(string $limiter, Request $request): array
{
    return collect(Arr::wrap(RateLimiter::limiter($limiter)($request)))
        ->map(fn (Limit $limit): string => $limit->key)
        ->all();
}

it('caps hosted-chat streaming per caller and per agent', function (string $limiter): void {
    $agent = Agent::factory()->create();
    $request = Request::create('/chat/support/stream/c1', 'POST', server: ['REMOTE_ADDR' => '203.0.113.9']);
    HostedChatAgent::remember($request, $agent);

    expect(rlspKeys($limiter, $request))
        ->toContain("{$limiter}|account|203.0.113.9")
        ->toContain("{$limiter}|agent|{$agent->id}");
})->with(['stream' => 'chat-stream', 'voice' => 'chat-voice']);

it('falls back to the caller cap alone when no agent is resolved', function (): void {
    $request = Request::create('/chat/unknown/stream/c1', 'POST', server: ['REMOTE_ADDR' => '203.0.113.9']);

    expect(rlspKeys('chat-stream', $request))->toBe(['chat-stream|account|203.0.113.9']);
});

it('puts one per-IP ceiling on every hosted-chat call', function (): void {
    $request = Request::create('/chat/support/appearance', 'GET', server: ['REMOTE_ADDR' => '203.0.113.9']);

    expect(rlspKeys('chat', $request))->toBe(['chat|ip|203.0.113.9']);
});
