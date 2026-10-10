<?php

use App\Identity\Workspace\Enums\UserType;
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

    app()->instance(LlmProviderInterface::class, $mock);
}

it('streams the answer through the runner and persists nothing', function (): void {
    $workspace = User::factory()->create(['type' => UserType::Workspace]);

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

it('serves a 200 SSE stream to the workspace owner', function (): void {
    $owner = User::factory()->create(['type' => UserType::Workspace, 'email_verified_at' => now()]);
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
    $owner = User::factory()->create(['type' => UserType::Workspace, 'email_verified_at' => now()]);
    $employee = User::factory()->create([
        'type' => UserType::Employee,
        'workspace_id' => $owner->id,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($employee)
        ->post(route('analytics.chat.stream'), ['message' => 'hi'])
        ->assertForbidden();
});

it('redirects guests to login', function (): void {
    $this->post(route('analytics.chat.stream'), ['message' => 'hi'])->assertRedirect();
});
