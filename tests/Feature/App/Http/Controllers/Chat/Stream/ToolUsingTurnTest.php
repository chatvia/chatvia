<?php

use App\Http\Responses\SseResponder;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\AgentActivity;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\Http\BufferedSseResponder;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * A full tool-using hosted-chat turn through the public SSE endpoint, the
 * seam the ToolLoopRunner unit tests cannot see: an auto-approved webhook
 * tool runs mid-stream, its raw result never reaches the browser, and the
 * final answer is stored with its tool-call record, audit row and invocation
 * counters (the queue runs inline). The model is faked at the provider
 * boundary: the first stream asks for the tool, the second answers.
 */
beforeEach(function (): void {
    config(['queue.default' => 'sync']);

    app()->instance(SseResponder::class, new BufferedSseResponder);
});

/**
 * The canonical two-turn tool conversation: model turn 1 requests
 * get_order_status(order_id=1234) via delta.tool_calls, turn 2 answers in
 * plain text (with an inline usage chunk, as providers send when
 * stream_options.include_usage is on).
 */
function tutBindTwoTurnLlm(): void
{
    bindFakeLlmProvider(new ScriptedLlmProvider(
        ScriptedLlmProvider::body([
            'id' => 'gen-tool-iteration',
            'choices' => [['delta' => ['tool_calls' => [[
                'index' => 0,
                'id' => 'call_order_1',
                'function' => ['name' => 'get_order_status', 'arguments' => '{"order_id":"1234"}'],
            ]]]]],
        ]),
        ScriptedLlmProvider::body(
            ['id' => 'gen-final-answer', 'choices' => [['delta' => ['content' => 'Your order 1234 ']]]],
            ['choices' => [['delta' => ['content' => 'has shipped.']]]],
            ['choices' => [], 'usage' => ['prompt_tokens' => 42, 'completion_tokens' => 12, 'total_tokens' => 54]],
        ),
    ));
}

/**
 * A webhook FunctionTool attached to the agent. Auto-approval is the stored
 * `functions.requires_approval = false` flag — the same column the approval
 * policy gates on at dispatch time (the DB default is true / gated).
 */
function tutWebhookFunction(Agent $agent): AgentFunction
{
    return AgentFunction::factory()->attachedTo($agent)->create([
        'internal_name' => 'get_order_status',
        'display_name' => 'Order status',
        'description' => 'Look up the current status of a customer order by its id.',
        'parameters_schema' => [
            'type' => 'object',
            'properties' => ['order_id' => ['type' => 'string']],
            'required' => ['order_id'],
        ],
        'webhook_url' => 'https://orders.example.test/status',
        'requires_approval' => false,
    ]);
}

it('runs an auto-approved webhook tool mid-stream and persists the assistant reply with its tool-call record', function (): void {
    tutBindTwoTurnLlm();
    Http::fake([
        'orders.example.test/*' => Http::response(['status' => 'shipped'], 200),
    ]);

    $agent = Agent::factory()->create(['is_public' => true]);
    $function = tutWebhookFunction($agent);

    $conversationId = (string) Str::uuid7();
    $response = $this->post(
        "/chat/{$agent->external_id}/stream/{$conversationId}",
        ['message' => 'What is the status of order 1234?'],
        ['Accept' => 'text/event-stream'],
    );

    $response->assertOk();
    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);

    $events = collect(BufferedSseResponder::events($response->streamedContent()));

    expect($events->where('type', 'start'))->not->toBeEmpty();

    $toolEvents = $events->where('type', 'tool')->values();
    expect($toolEvents->pluck('data.phase')->all())->toBe(['start', 'end'])
        ->and($toolEvents[0]['data']['name'])->toBe('get_order_status')
        ->and($toolEvents[0]['data']['args'])->toBe('{"order_id":"1234"}')
        ->and($toolEvents[1]['data'])->not->toHaveKey('result')
        ->and($response->streamedContent())->not->toContain('"status":"shipped"')
        ->and($response->streamedContent())->not->toContain('\\"status\\":\\"shipped\\"');

    $answer = $events->where('type', 'delta')->pluck('data.text')->implode('');
    expect($answer)->toBe('Your order 1234 has shipped.');

    $done = $events->where('type', 'done')->last();
    expect($done['data']['reason'])->toBe('complete');

    Http::assertSent(fn (ClientRequest $request): bool => Str::startsWith($request->url(), 'https://orders.example.test/status')
        && $request['order_id'] === '1234');

    $conversation = Conversation::where('external_id', $conversationId)->first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->source)->toBe(ConversationSource::HostedChat);

    $assistantMessages = Message::query()
        ->where('chat_conversation_id', $conversation->id)
        ->where('role', 'assistant')
        ->get();

    expect($assistantMessages)->toHaveCount(1)
        ->and($assistantMessages[0]->content)->toBe('Your order 1234 has shipped.')
        ->and($assistantMessages[0]->metadata['tool_calls'])->toBe(['get_order_status'])
        ->and($assistantMessages[0]->prompt_tokens)->toBe(42)
        ->and($assistantMessages[0]->completion_tokens)->toBe(12)
        ->and(ToolApprovalCheckpoint::where('agent_id', $agent->id)->count())->toBe(0);

    $audit = AgentActivity::where('agent_id', $agent->id)->get();
    expect($audit)->toHaveCount(1)
        ->and($audit[0]->tool_name)->toBe('get_order_status')
        ->and($audit[0]->outcome)->toBe(ToolCallOutcome::Auto)
        ->and((bool) $audit[0]->is_gated)->toBeFalse()
        ->and((bool) $audit[0]->is_error)->toBeFalse()
        ->and($function->refresh()->invocation_count)->toBe(1)
        ->and((int) DB::table('agent_function')
            ->where('agent_id', $agent->id)
            ->where('function_id', $function->id)
            ->value('invocation_count'))->toBe(1);
});
