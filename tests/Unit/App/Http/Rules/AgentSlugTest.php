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

it('rejects a slug that is too short, too long or not URL-safe', function (string $slug): void {
    expect(asFails(Agent::factory()->create(), $slug))->toBeTrue();
})->with([
    'too short' => ['ab'],
    'too long' => [Str::repeat('a', 64)],
    'not URL-safe' => ['Upper'],
]);

it('accepts a free slug or the agent\'s own, and refuses another agent\'s external id', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['external_id' => 'abc123def456gh']);

    expect(asFails($agent, 'support-bot'))->toBeFalse()
        ->and(asFails($agent, $agent->slug))->toBeFalse()
        ->and(asFails($agent, $other->external_id))->toBeTrue();
});
