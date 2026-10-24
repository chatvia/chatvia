<?php

use App\Http\Rules\AgentSlug;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * An agent's chat-address slug: URL-safe, 3–63 characters, and not used by
 * any other agent as its slug or its external id (both name a chat).
 * Another agent's slug is refused at the endpoint (RenameAgentControllerTest).
 */
function asFails(Agent $agent, mixed $slug): bool
{
    return Validator::make(['slug' => $slug], ['slug' => [new AgentSlug($agent)]])->fails();
}

it('accepts a free, well-formed slug', function (): void {
    $agent = Agent::factory()->create();

    expect(asFails($agent, 'support-bot'))->toBeFalse()
        ->and(asFails($agent, $agent->slug))->toBeFalse();
});

it('rejects a malformed slug', function (string $slug): void {
    expect(asFails(Agent::factory()->create(), $slug))->toBeTrue();
})->with(['ab', 'Upper', 'has space', '-dash', 'dash-', 'under_score', Str::repeat('a', 64)]);

it('rejects another agent\'s external id, which also names a chat', function (): void {
    $other = Agent::factory()->create(['external_id' => 'abc123def456gh']);

    expect(asFails(Agent::factory()->create(), $other->external_id))->toBeTrue();
});
