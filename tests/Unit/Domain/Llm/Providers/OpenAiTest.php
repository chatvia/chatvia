<?php

declare(strict_types=1);

use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\OpenAi;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Llm\GuzzleRecorder;

/**
 * OpenAI streams every chat turn through its Responses API (the body is
 * written by ResponsesPayload) and keeps single calls on Chat Completions. A
 * summary of the thinking is asked for only when the turn shows it, and
 * dropped for good when the organization may not have one.
 */
function oaiClient(): OpenAi
{
    return new OpenAi(LlmProvider::factory()->make(), 'https://llm.test/v1');
}

/** Stream one turn at the given effort against the given replies. */
function oaiStream(?string $effort, array $responses = [], bool $show = false): GuzzleRecorder
{
    $recorder = GuzzleRecorder::answering(...($responses ?: [new Response(200, [], "data: [DONE]\n\n")]));

    $settings = ['top_k' => 40, 'max_tokens' => 100]
        + ($effort !== null ? ['reasoning_effort' => $effort] : [])
        + ($show ? ['show_reasoning' => true] : []);
    oaiClient()->stream('gpt-6.1-sol', [['role' => 'user', 'content' => 'hi']], $settings)->getBody();

    return $recorder;
}

it('streams every chat turn through the Responses API, with a summary only when the turn shows the thinking', function (?string $effort, bool $show, ?array $reasoning): void {
    $recorder = oaiStream($effort, show: $show);

    expect((string) $recorder->request()->getUri())->toBe('https://llm.test/v1/responses')
        ->and($recorder->body()['reasoning'] ?? null)->toBe($reasoning)
        ->and($recorder->body())->not->toHaveKey('show_reasoning');
})->with([
    'no effort: none sent' => [null, false, null],
    'an effort, thinking not shown' => ['low', false, ['effort' => 'low']],
    'an effort, thinking shown' => ['medium', true, ['effort' => 'medium', 'summary' => 'auto']],
]);

it('keeps single calls on Chat Completions', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    oaiClient()->completion(['model' => 'gpt-6.1-sol', 'messages' => []]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://llm.test/v1/chat/completions');
});

it('asks again without a summary when the organization may not have one, and skips it from then on', function (): void {
    $refusal = json_encode(['error' => [
        'message' => 'Your organization must be verified to generate reasoning summaries.',
        'type' => 'invalid_request_error',
        'param' => 'reasoning.summary',
        'code' => 'unsupported_value',
    ]]);

    $recorder = oaiStream('high', [new Response(400, [], $refusal), new Response(200, [], "data: [DONE]\n\n")], show: true);

    expect($recorder->history)->toHaveCount(2)
        ->and($recorder->body(1)['reasoning'])->toBe(['effort' => 'high'])
        ->and(oaiStream('high', show: true)->body()['reasoning'])->toBe(['effort' => 'high']);
});

it('does not hide other errors behind a retry', function (): void {
    oaiStream('high', [new Response(400, [], '{"error":{"message":"Unknown parameter"}}')], show: true);
})->throws(ClientException::class);
