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
 * events and ends with a done event, nothing is stored as a chat, and once
 * the model has used its last tool round it must answer without tools.
 * The model is a canned OpenAI-style SSE stream.
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

it('answers without tools once the model has used its last tool round', function (): void {
    config(['chatvia.agent.tools.max_iterations' => 1]);
    $workspace = User::factory()->create()->workspace();

    $settingsSeen = [];
    $provider = Mockery::mock(ChatCompletionProvider::class);
    $provider->shouldReceive('stream')->twice()->andReturnUsing(function (string $model, array $messages, array $settings) use (&$settingsSeen): Psr7Response {
        $settingsSeen[] = $settings;

        return new Psr7Response(200, [], count($settingsSeen) === 1
            ? acrSse('{"choices":[{"delta":{"tool_calls":[{"index":0,"id":"call_1","function":{"name":"no_such_tool","arguments":"{}"}}]}}]}')
            : acrSse('{"choices":[{"delta":{"content":"Here is what I found."}}]}'));
    });
    bindFakeLlmProvider($provider);

    $events = iterator_to_array(app(AnalyticsChatRunner::class)->events($workspace, new AnalyticsChatRequestData(message: 'how many messages this cycle?')), preserve_keys: false);

    expect(acrText($events))->toContain('Here is what I found.')
        ->and(array_column($settingsSeen, 'tool_choice'))->toBe(['auto', 'none']);
});
