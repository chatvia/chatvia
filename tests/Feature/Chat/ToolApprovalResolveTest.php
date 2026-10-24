<?php

use App\Http\Responses\SseResponder;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Http\BufferedSseResponder;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * POST chat/{agentSlug}/tools/{approval}/resolve resumes a hosted-chat turn
 * that paused on a gated tool call. Only the visitor whose turn paused, on the
 * agent it paused on, may resolve it, and only once: otherwise one visitor
 * could approve another's tool call.
 */
beforeEach(function (): void {
    config(['queue.default' => 'sync']);
    app()->instance(SseResponder::class, new BufferedSseResponder);
    Http::fake(['orders.example.test/*' => Http::response(['status' => 'shipped'])]);

    $this->agent = Agent::factory()->create(['is_public' => true]);
    AgentFunction::factory()->attachedTo($this->agent)->requiringApproval()->create([
        'internal_name' => 'cancel_order',
        'description' => 'Cancel a customer order by its id.',
        'parameters_schema' => ['type' => 'object', 'properties' => ['order_id' => ['type' => 'string']], 'required' => ['order_id']],
        'webhook_url' => 'https://orders.example.test/cancel',
    ]);

    // Round 1 asks for the gated call; round 2 answers after the decision.
    bindFakeLlmProvider(new ScriptedLlmProvider(
        ScriptedLlmProvider::body(['id' => 'gen-1', 'choices' => [['delta' => ['tool_calls' => [[
            'index' => 0,
            'id' => 'call_cancel_1',
            'function' => ['name' => 'cancel_order', 'arguments' => '{"order_id":"1234"}'],
        ]]]]]]),
        ScriptedLlmProvider::body(['id' => 'gen-2', 'choices' => [['delta' => ['content' => 'Order 1234 is cancelled.']]]]),
    ));

    // The visitor's turn pauses on the gated call.
    $events = tarEvents($this->post(
        "/chat/{$this->agent->slug}/stream/".Str::uuid(),
        ['message' => 'Please cancel order 1234'],
        ['Accept' => 'text/event-stream'],
    )->assertOk());

    expect($events->where('type', 'tool')->pluck('data.phase')->all())->toContain('awaiting_approval');

    $this->checkpoint = ToolApprovalCheckpoint::query()->where('agent_id', $this->agent->id)->sole();
});

/** @return Collection<int, array<string, mixed>> */
function tarEvents(TestResponse $response): Collection
{
    return collect(BufferedSseResponder::events($response->streamedContent()));
}

function tarResolve(Agent $agent, ToolApprovalCheckpoint $checkpoint): TestResponse
{
    return test()->post(
        "/chat/{$agent->slug}/tools/{$checkpoint->external_id}/resolve",
        ['decisions' => ['call_cancel_1' => 'approve']],
        ['Accept' => 'text/event-stream'],
    );
}

it('resumes the visitor\'s own paused turn and runs the approved call', function (): void {
    $events = tarEvents(tarResolve($this->agent, $this->checkpoint)->assertOk());

    expect($events->where('type', 'delta')->pluck('data.text')->implode(''))->toBe('Order 1234 is cancelled.')
        ->and($events->where('type', 'done')->last()['data']['reason'])->toBe('complete')
        ->and($this->checkpoint->fresh()->status)->toBe(ToolApprovalStatus::Resolved);

    Http::assertSent(fn (ClientRequest $request): bool => Str::startsWith($request->url(), 'https://orders.example.test/cancel'));
});

it('answers 404 for another visitor\'s paused turn and leaves it pending', function (): void {
    // A fresh browser session is a different visitor.
    $this->flushSession();

    tarResolve($this->agent, $this->checkpoint)->assertNotFound();

    expect($this->checkpoint->fresh()->status)->toBe(ToolApprovalStatus::Pending);
    Http::assertNothingSent();
});

it('answers 404 for a paused turn of another agent and leaves it pending', function (): void {
    $other = Agent::factory()->create(['is_public' => true]);

    tarResolve($other, $this->checkpoint)->assertNotFound();

    expect($this->checkpoint->fresh()->status)->toBe(ToolApprovalStatus::Pending);
    Http::assertNothingSent();
});

it('resolves a paused turn only once', function (): void {
    tarResolve($this->agent, $this->checkpoint)->assertOk()->streamedContent();

    $second = tarEvents(tarResolve($this->agent, $this->checkpoint)->assertOk());

    expect($second->firstWhere('type', 'error')['data']['message'] ?? null)->toBe('This action was already handled or has expired.')
        ->and($second->where('type', 'delta'))->toBeEmpty();
    Http::assertSentCount(1);
});
