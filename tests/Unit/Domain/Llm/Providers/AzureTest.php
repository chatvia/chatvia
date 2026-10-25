<?php

declare(strict_types=1);

use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Azure;
use Domain\Llm\Services\ModelsDevCatalog;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\Llm\GuzzleRecorder;

/**
 * Azure OpenAI takes its key in the `api-key` header. Deployments of OpenAI's
 * models stream through the Responses API; the others, and names the catalog
 * does not know, through Chat Completions.
 */
function azureClient(): Azure
{
    Cache::memo()->forever(ModelsDevCatalog::CACHE_KEY, [
        'checked_at' => now()->addYears(100)->getTimestamp(),
        'providers' => ['azure' => [
            'gpt-4.1' => ['canonical_model_id' => 'openai/gpt-4.1'],
            'deepseek-v4-flash' => ['canonical_model_id' => 'deepseek/deepseek-v4-flash'],
        ]],
    ]);

    return new Azure(LlmProvider::factory()->make(), 'https://res.openai.azure.com/openai/v1', 'az-key');
}

it('streams each deployment through the API its model takes, with the key in the api-key header', function (string $model, string $endpoint): void {
    $recorder = GuzzleRecorder::answering(new Response(200, [], "data: [DONE]\n\n"));

    azureClient()->stream($model, [['role' => 'user', 'content' => 'hi']], ['top_k' => 40, 'max_tokens' => 100])->getBody();

    expect((string) $recorder->request()->getUri())->toBe("https://res.openai.azure.com/openai/v1/{$endpoint}")
        ->and($recorder->request()->getHeaderLine('api-key'))->toBe('az-key')
        ->and($recorder->request()->hasHeader('Authorization'))->toBeFalse()
        ->and($recorder->body())->not->toHaveKey('top_k');
})->with([
    'an OpenAI model' => ['gpt-4.1', 'responses'],
    'another lab\'s model' => ['deepseek-v4-flash', 'chat/completions'],
    'a deployment the catalog does not know' => ['my-gpt', 'chat/completions'],
]);

it('caps an OpenAI model\'s reply with max_completion_tokens, and the others\' with max_tokens', function (string $model, string $cap): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    azureClient()->completion(['model' => $model, 'messages' => [], 'max_tokens' => 100]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://res.openai.azure.com/openai/v1/chat/completions'
        && $request[$cap] === 100
        && count(array_intersect(array_keys($request->data()), ['max_tokens', 'max_completion_tokens'])) === 1
        && $request->header('api-key') === ['az-key']);
})->with([
    ['gpt-4.1', 'max_completion_tokens'],
    ['deepseek-v4-flash', 'max_tokens'],
]);
