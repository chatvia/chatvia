<?php

use App\Identity\Workspace\Models\User;
use Domain\Llm\Contracts\LlmProviderInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;

/**
 * The owner's "Ask your data" chat streams its answer as server-sent events.
 * It reads every number of the workspace, so only the workspace owner may
 * use it. What the stream holds is in AnalyticsChatRunnerTest.
 */
it('serves an SSE stream to the workspace owner', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $provider = Mockery::mock(LlmProviderInterface::class);
    $provider->shouldReceive('stream')->andReturn(new Psr7Response(200, [], "data: [DONE]\n\n"));
    bindFakeLlmProvider($provider);

    $response = $this->actingAs($owner)->post(
        route('analytics.chat.stream'),
        ['message' => 'hi'],
        ['Accept' => 'text/event-stream'],
    );

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');
});

it('forbids non-owner team members', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $employee = User::factory()->employee($owner)->create(['email_verified_at' => now()]);

    $this->actingAs($employee)
        ->post(route('analytics.chat.stream'), ['message' => 'hi'])
        ->assertForbidden();
});
