<?php

use App\Http\Rules\AgentSlug;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Validator;

/**
 * An agent's chat-address slug: URL-safe, 3–63 characters, and not used by
 * any other agent as its slug or its external id (both name a chat).
 */
function slugFails(Agent $agent, mixed $slug): bool
{
    return Validator::make(['slug' => $slug], ['slug' => [new AgentSlug($agent)]])->fails();
}

it('accepts a free, well-formed slug', function (): void {
    $agent = Agent::factory()->create();

    expect(slugFails($agent, 'support-bot'))->toBeFalse()
        ->and(slugFails($agent, $agent->slug))->toBeFalse();
});

it('rejects a malformed slug', function (string $slug): void {
    expect(slugFails(Agent::factory()->create(), $slug))->toBeTrue();
})->with(['ab', 'Upper', 'has space', '-dash', 'dash-', 'under_score', str_repeat('a', 64)]);

it('rejects another agent\'s slug or external id', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['slug' => 'taken-name', 'external_id' => 'abc123def456gh']);

    expect(slugFails($agent, 'taken-name'))->toBeTrue()
        ->and(slugFails($agent, $other->external_id))->toBeTrue();
});
