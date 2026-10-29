<?php

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Data\Chat\AnalyticsChatRequestData;
use Domain\Analytics\Services\Chat\AnalyticsChatRunner;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Llm\Contracts\ChatCompletionProvider;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Support\Facades\DB;

/**
 * The owner's "Ask your data" chat: the model's answer streams as delta
 * events and ends with a done event, and nothing is stored as a chat. The
 * model is a canned OpenAI-style SSE stream.
 */
function acrSse(string ...$chunks): string
{
    return implode('', array_map(fn (string $chunk): string => "data: {$chunk}\n\n", [...$chunks, '[DONE]']));
}

/** @param  list<StreamEvent>  $events */
function acrText(array $events): string
{
    return collect($events)
        ->filter(fn (StreamEvent $event): bool => $event->type === EventType::Delta)
        ->map(fn (StreamEvent $event): mixed => $event->data['text'])
        ->implode('');
}

it('streams the answer and stores no chat', function (): void {
    $workspace = User::factory()->create()->workspace();
    $provider = Mockery::mock(ChatCompletionProvider::class);
    $provider->shouldReceive('stream')->andReturn(new Psr7Response(200, [], acrSse('{"choices":[{"delta":{"content":"You received 15 messages this cycle."}}]}')));
    bindFakeLlmProvider($provider);
    $tables = ['chat_conversations', 'chat_sessions', 'chat_messages'];
    $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();

    $events = iterator_to_array(app(AnalyticsChatRunner::class)->events($workspace, new AnalyticsChatRequestData(message: 'how many messages this cycle?')), preserve_keys: false);

    expect(acrText($events))->toContain('You received 15 messages this cycle.')
        ->and(collect($events)->contains(fn (StreamEvent $event): bool => $event->type === EventType::Done))->toBeTrue()
        ->and(collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all())->toBe($before);
});
