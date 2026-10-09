<?php

use Domain\Integration\Builtin\Data\Perplexity\PerplexityAgentRequestData;
use Domain\Integration\Builtin\Data\Perplexity\PerplexitySonarRequestData;
use Domain\Integration\Builtin\Exceptions\PerplexityException;
use Domain\Integration\Builtin\Services\PerplexityClient;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.perplexity.base_url' => 'https://perplexity.example.test/',
        'services.perplexity.agent_model' => 'perplexity/sonar',
    ]);
});

it('asks a Sonar model and returns the answer without its reasoning', function (): void {
    Http::fake(['perplexity.example.test/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => '<think>hmm</think> Paris. ']]],
        'search_results' => [
            ['title' => 'Atlas', 'url' => 'https://atlas.example'],
            ['title' => 'Again', 'url' => 'https://atlas.example'],
        ],
    ])]);

    $answer = app(PerplexityClient::class)->sonar('key', new PerplexitySonarRequestData('sonar-pro', 'capital of France?', 'Be brief.', 45));

    expect($answer->answer)->toBe('Paris.')
        ->and($answer->citations)->toHaveCount(1)
        ->and($answer->citations[0]->title)->toBe('Atlas')
        ->and($answer->sandbox)->toBeNull();

    Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader('Authorization', 'Bearer key')
        && $request->data() === [
            'model' => 'sonar-pro',
            'messages' => [
                ['role' => 'system', 'content' => 'Be brief.'],
                ['role' => 'user', 'content' => 'capital of France?'],
            ],
        ]);
});

it('runs one Agent API tool and parses the sandbox run', function (): void {
    Http::fake(['perplexity.example.test/v1/agent' => Http::response([
        'output' => [
            ['type' => 'sandbox_results', 'language' => 'python', 'code' => 'print(1)', 'status' => 'completed', 'results' => [['stdout' => "1\n", 'exit_code' => 0]]],
            ['type' => 'message', 'role' => 'assistant', 'content' => [['text' => 'It printed 1.']]],
        ],
    ])]);

    $answer = app(PerplexityClient::class)->agent('key', new PerplexityAgentRequestData('sandbox', 'print one', 'Run it.', 240));

    expect($answer->answer)->toBe('It printed 1.')
        ->and($answer->citations)->toBe([])
        ->and($answer->sandbox->language)->toBe('python')
        ->and($answer->sandbox->executions[0]->stdout)->toBe("1\n")
        ->and($answer->sandbox->executions[0]->stderr)->toBe('')
        ->and($answer->sandbox->executions[0]->exitCode)->toBe(0);

    Http::assertSent(fn (ClientRequest $request): bool => $request->data() === [
        'model' => 'perplexity/sonar',
        'input' => 'print one',
        'tools' => [['type' => 'sandbox']],
        'instructions' => 'Run it.',
    ]);
});

it('fails on an upstream error or an empty answer', function (string $method, array $body, int $status, string $message): void {
    Http::fake(['perplexity.example.test/*' => Http::response($body, $status)]);

    $call = $method === 'sonar'
        ? fn () => app(PerplexityClient::class)->sonar('key', new PerplexitySonarRequestData('sonar', 'q?', null, 45))
        : fn () => app(PerplexityClient::class)->agent('key', new PerplexityAgentRequestData('web_search', 'q?', null, 60));

    expect($call)->toThrow(PerplexityException::class, $message);
})->with([
    'sonar upstream error' => ['sonar', [], 500, 'Perplexity returned HTTP 500.'],
    'agent upstream error' => ['agent', [], 401, 'Perplexity returned HTTP 401.'],
    'sonar empty answer, even with sources' => ['sonar', ['choices' => [['message' => ['content' => '']]], 'citations' => ['https://x.example']], 200, 'Perplexity returned no answer.'],
    'agent no answer and no sources' => ['agent', ['output' => []], 200, 'Perplexity returned no answer.'],
]);
