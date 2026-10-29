<?php

use App\Http\Responses\SseResponder;
use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Http\BufferedSseResponder;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * POST chat/{agentSlug}/tools/{approval}/resolve resumes a hosted-chat turn
 * that paused on a gated tool call. Only the signed-in chatter whose turn
 * paused, on the agent it paused on, may resolve it: otherwise one visitor
 * could approve another's tool call. An anonymous visitor is never offered a
 * gated tool at all, since it runs with the workspace's own credentials.
 * That it resolves only once is the engine's rule.
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

    bindFakeLlmProvider(new ScriptedLlmProvider(
        ScriptedLlmProvider::body(['id' => 'gen-1', 'choices' => [['delta' => ['tool_calls' => [[
            'index' => 0,
            'id' => 'call_cancel_1',
            'function' => ['name' => 'cancel_order', 'arguments' => '{"order_id":"1234"}'],
        ]]]]]]),
        ScriptedLlmProvider::body(['id' => 'gen-2', 'choices' => [['delta' => ['content' => 'Order 1234 is cancelled.']]]]),
    ));

    $this->chatter = Chatter::factory()->ofWorkspace($this->agent->workspace)->create();
    $this->actingAs($this->chatter, 'chat');
    $this->post(
        "/chat/{$this->agent->slug}/stream/".Str::uuid7(),
        ['message' => 'Please cancel order 1234'],
        ['Accept' => 'text/event-stream'],
    )->assertOk()->streamedContent();

    $this->checkpoint = ToolApprovalCheckpoint::query()->where('agent_id', $this->agent->id)->sole();
});

/** @return Collection<int, array<string, mixed>> */
function rtacEvents(TestResponse $response): Collection
{
    return collect(BufferedSseResponder::events($response->streamedContent()));
}

function rtacResolve(Agent $agent, ToolApprovalCheckpoint $checkpoint): TestResponse
{
    return test()->post(
        "/chat/{$agent->slug}/tools/{$checkpoint->external_id}/resolve",
        ['decisions' => ['call_cancel_1' => 'approve']],
        ['Accept' => 'text/event-stream'],
    );
}

it('resumes the visitor\'s own paused turn and runs the approved call', function (): void {
    $events = rtacEvents(rtacResolve($this->agent, $this->checkpoint)->assertOk());

    expect($events->where('type', 'delta')->pluck('data.text')->implode(''))->toBe('Order 1234 is cancelled.')
        ->and($events->where('type', 'done')->last()['data']['reason'])->toBe('complete')
        ->and($this->checkpoint->fresh()->status)->toBe(ToolApprovalStatus::Resolved);

    Http::assertSent(fn (ClientRequest $request): bool => Str::startsWith($request->url(), 'https://orders.example.test/cancel')
        && $request->header('X-Chatvia-Chatter-Id') === [$this->chatter->external_id]);
});

it('never offers an anonymous visitor a tool that needs approval', function (): void {
    $this->checkpoint->delete();
    Auth::guard('chat')->logout();
    $this->flushSession();
    bindFakeLlmProvider($llm = new ScriptedLlmProvider(
        ScriptedLlmProvider::body(['id' => 'gen-3', 'choices' => [['delta' => ['content' => 'I cannot do that here.']]]]),
    ));

    $this->post(
        "/chat/{$this->agent->slug}/stream/".Str::uuid7(),
        ['message' => 'Please cancel order 1234'],
        ['Accept' => 'text/event-stream'],
    )->assertOk()->streamedContent();

    $offered = collect($llm->sentSettings)->flatMap(fn (array $settings): array => array_column(array_column($settings['tools'] ?? [], 'function'), 'name'));
    expect($offered)->not->toContain('cancel_order')
        ->and(ToolApprovalCheckpoint::query()->where('agent_id', $this->agent->id)->count())->toBe(0);
    Http::assertNothingSent();
});

it('answers 404 for a paused turn of another agent or another chatter and leaves it pending', function (): void {
    $other = Agent::factory()->create(['is_public' => true]);

    rtacResolve($other, $this->checkpoint)->assertNotFound();

    $this->flushSession();
    $this->actingAs(Chatter::factory()->ofWorkspace($this->agent->workspace)->create(), 'chat');

    rtacResolve($this->agent, $this->checkpoint)->assertNotFound();

    expect($this->checkpoint->fresh()->status)->toBe(ToolApprovalStatus::Pending);
    Http::assertNothingSent();
});
