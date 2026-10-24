<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Which of the workspace's functions an agent may call, through the public
 * API: attach one by its id, list them, detach one (the function itself
 * stays). Another workspace's function is a field error, never attached.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/functions";
});

it('attaches a workspace function to the agent, lists it, and detaches it but keeps the function', function (): void {
    $function = AgentFunction::factory()->forOwner($this->owner)->create();

    $this->withToken($this->token)
        ->postJson($this->uri, ['function_id' => $function->external_id])
        ->assertOk()
        ->assertJsonPath('data.id', $function->external_id);

    expect($this->agent->functions()->pluck('functions.id')->all())->toBe([$function->id]);

    $this->withToken($this->token)
        ->getJson($this->uri)
        ->assertOk()
        ->assertJsonPath('data.0.id', $function->external_id);

    $this->withToken($this->token)->deleteJson("{$this->uri}/{$function->external_id}")->assertNoContent();

    expect($this->agent->functions()->exists())->toBeFalse()
        ->and(AgentFunction::whereKey($function->id)->exists())->toBeTrue();
});

it("rejects another workspace's function as a field error and attaches nothing", function (): void {
    $theirs = AgentFunction::factory()->forOwner(User::factory()->create())->create();

    $this->withToken($this->token)
        ->postJson($this->uri, ['function_id' => $theirs->external_id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['function_id']);

    expect($this->agent->functions()->exists())->toBeFalse();
});
