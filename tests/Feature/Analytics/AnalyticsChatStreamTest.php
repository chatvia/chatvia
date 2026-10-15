<?php

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Data\Chat\AnalyticsChatRequestData;
use Domain\Analytics\Services\Chat\AnalyticsChatRunner;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Llm\Contracts\LlmProviderInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Support\Facades\DB;

/**
 * Bind a fake chat provider that returns a canned OpenAI-style SSE stream so the
 * runner streams a final answer without a real LLM call or any tool round.
 */
function fakeChatProvider(string $sse): void
{
    $mock = Mockery::mock(LlmProviderInterface::class);
    $mock->shouldReceive('stream')->andReturn(new Psr7Response(200, [], $sse));

    bindFakeLlmProvider($mock);
}

it('streams the answer through the runner and persists nothing', function (): void {
    $workspace = User::factory()->create()->workspace();

    fakeChatProvider(
        'data: {"choices":[{"delta":{"content":"You received 15 messages this cycle."}}]}'."\n\n".
        'data: [DONE]'."\n\n"
    );

    $before = [
        'conversations' => DB::table('chat_conversations')->count(),
        'sessions' => DB::table('chat_sessions')->count(),
        'messages' => DB::table('chat_messages')->count(),
    ];

    $events = iterator_to_array(app(AnalyticsChatRunner::class)->events(
        $workspace,
        new AnalyticsChatRequestData(message: 'how many messages this cycle?'),
    ));

    $text = collect($events)
        ->filter(fn ($e): bool => $e->type === EventType::Delta)
        ->map(fn ($e): mixed => $e->data['text'])
        ->implode('');

    expect($text)->toContain('You received 15 messages this cycle.');
    expect(collect($events)->contains(fn ($e): bool => $e->type === EventType::Done))->toBeTrue();

    // Ephemeral: not a single new conversation/session/message row.
    expect(DB::table('chat_conversations')->count())->toBe($before['conversations']);
    expect(DB::table('chat_sessions')->count())->toBe($before['sessions']);
    expect(DB::table('chat_messages')->count())->toBe($before['messages']);
});

it('answers without tools once the model has used its last tool round', function (): void {
    config(['chatvia.agent.tools.max_iterations' => 1]);
    $workspace = User::factory()->create()->workspace();

    $settingsSeen = [];
    $mock = Mockery::mock(LlmProviderInterface::class);
    $mock->shouldReceive('stream')->twice()->andReturnUsing(function (string $model, array $messages, array $settings) use (&$settingsSeen): Psr7Response {
        $settingsSeen[] = $settings;

        return new Psr7Response(200, [], count($settingsSeen) === 1
            ? 'data: {"choices":[{"delta":{"tool_calls":[{"index":0,"id":"call_1","function":{"name":"no_such_tool","arguments":"{}"}}]}}]}'."\n\n".'data: [DONE]'."\n\n"
            : 'data: {"choices":[{"delta":{"content":"Here is what I found."}}]}'."\n\n".'data: [DONE]'."\n\n");
    });
    bindFakeLlmProvider($mock);

    $events = iterator_to_array(app(AnalyticsChatRunner::class)->events(
        $workspace,
        new AnalyticsChatRequestData(message: 'how many messages this cycle?'),
    ), preserve_keys: false);

    $text = collect($events)
        ->filter(fn ($e): bool => $e->type === EventType::Delta)
        ->map(fn ($e): mixed => $e->data['text'])
        ->implode('');

    expect($text)->toContain('Here is what I found.')
        ->and(array_column($settingsSeen, 'tool_choice'))->toBe(['auto', 'none']);
});

it('serves a 200 SSE stream to the workspace owner', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    fakeChatProvider("data: [DONE]\n\n");

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

it('redirects guests to login', function (): void {
    $this->post(route('analytics.chat.stream'), ['message' => 'hi'])->assertRedirect();
});
