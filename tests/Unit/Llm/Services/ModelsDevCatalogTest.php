<?php

declare(strict_types=1);

use Domain\Llm\Data\Catalog\ModelFactsData;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The models.dev catalog gives each model's facts: whether it reasons, the
 * effort values it takes, whether it takes a temperature, calls tools and
 * keeps to a JSON schema, its longest reply.
 * It is downloaded at most once a day and only when it changed (ETag); a
 * failure or the switch being off gives no facts. The entries below follow
 * the catalog's shape.
 */
function mdCatalog(): array
{
    return [
        'providers' => [
            'openai' => ['id' => 'openai', 'models' => [
                'gpt-6-luna' => ['id' => 'gpt-6-luna', 'name' => 'GPT-6 Luna', 'reasoning' => true, 'temperature' => false,
                    'reasoning_options' => [['type' => 'effort', 'values' => ['none', 'low', 'medium', 'high', 'xhigh', 'max']]],
                    'tool_call' => true, 'structured_output' => true,
                    'limit' => ['context' => 400000, 'output' => 128000], 'cost' => ['input' => 1.25, 'output' => 10]],
                'gpt-4.1-mini' => ['id' => 'gpt-4.1-mini', 'reasoning' => false, 'temperature' => true],
            ]],
            'openrouter' => ['id' => 'openrouter', 'models' => [
                'anthropic/claude-x' => ['id' => 'anthropic/claude-x', 'reasoning' => true, 'temperature' => true,
                    'reasoning_options' => [['type' => 'toggle'], ['type' => 'budget_tokens', 'min' => 1024]]],
            ]],
            'some-other-host' => ['id' => 'some-other-host', 'models' => ['gpt-6-luna' => ['reasoning' => false]]],
        ],
        'models' => [
            'qwen/qwen3-8b' => ['id' => 'qwen/qwen3-8b', 'reasoning' => true, 'temperature' => true,
                'reasoning_options' => [['type' => 'effort', 'values' => ['none', 'low', 'medium', 'high']]]],
        ],
    ];
}

beforeEach(function (): void {
    config(['chatvia.ai.models_dev.enabled' => true]);
});

it('gives each model the facts its provider lists', function (): void {
    Http::fake([ModelsDevCatalog::URL => Http::response(mdCatalog(), 200, ['ETag' => '"v1"'])]);
    $catalog = app(ModelsDevCatalog::class);

    expect($catalog->facts(LlmProviderKind::OpenAi, 'gpt-6-luna'))->toEqual(new ModelFactsData(true, ['none', 'low', 'medium', 'high', 'xhigh', 'max'], false, 128000, callsTools: true, takesResponseSchema: true))
        ->and($catalog->facts(LlmProviderKind::OpenAi, 'gpt-4.1-mini'))->toEqual(new ModelFactsData(false, null, true))
        // Reasons without named effort values: no values to send.
        ->and($catalog->facts(LlmProviderKind::OpenRouter, 'anthropic/claude-x'))->toEqual(new ModelFactsData(true, null, true))
        // A server of your own: the catalog's general models, by id.
        ->and($catalog->facts(LlmProviderKind::Custom, 'qwen/qwen3-8b')?->efforts)->toBe(['none', 'low', 'medium', 'high'])
        ->and($catalog->facts(LlmProviderKind::OpenAi, 'not-listed'))->toBeNull();

    Http::assertSentCount(1);
});

it('keeps only the fields the app reads', function (): void {
    Http::fake([ModelsDevCatalog::URL => Http::response(mdCatalog(), 200, ['ETag' => '"v1"'])]);

    expect(app(ModelsDevCatalog::class)->entry(LlmProviderKind::OpenAi, 'gpt-6-luna'))->toBe([
        'reasoning' => true,
        'temperature' => false,
        'reasoning_options' => [['type' => 'effort', 'values' => ['none', 'low', 'medium', 'high', 'xhigh', 'max']]],
        'tool_call' => true,
        'structured_output' => true,
        'limit' => ['context' => 400000, 'output' => 128000],
    ]);
});

it('downloads the catalog again only when it changed', function (): void {
    Http::fakeSequence()
        ->push(mdCatalog(), 200, ['ETag' => '"v1"'])
        ->push('', 304);
    $catalog = app(ModelsDevCatalog::class);

    $catalog->facts(LlmProviderKind::OpenAi, 'gpt-6-luna');
    $this->travel(25)->hours();

    expect($catalog->facts(LlmProviderKind::OpenAi, 'gpt-6-luna')?->reasoning)->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->header('If-None-Match') === ['"v1"']);
    Http::assertSentCount(2);
});

it('downloads the whole catalog again when the facts kept were cut to other fields', function (): void {
    Cache::forever('llm:models-dev:facts', [
        'etag' => '"v1"',
        'checked_at' => now()->getTimestamp(),
        'providers' => ['openai' => ['gpt-6-luna' => ['reasoning' => true, 'temperature' => false]]],
    ]);
    Http::fake([ModelsDevCatalog::URL => Http::response(mdCatalog(), 200, ['ETag' => '"v2"'])]);
    $catalog = app(ModelsDevCatalog::class);

    $catalog->refresh();

    expect($catalog->facts(LlmProviderKind::OpenAi, 'gpt-6-luna')?->maxOutputTokens)->toBe(128000);

    Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('If-None-Match'));
});

it('reads a stored entry without downloading', function (): void {
    Http::fake([ModelsDevCatalog::URL => Http::response(mdCatalog(), 200, ['ETag' => '"v1"'])]);
    $catalog = app(ModelsDevCatalog::class);

    expect($catalog->storedEntry(LlmProviderKind::OpenAi, 'gpt-6-luna'))->toBeNull();
    Http::assertNothingSent();

    $catalog->refresh();
    $this->travel(25)->hours();

    expect($catalog->storedEntry(LlmProviderKind::OpenAi, 'gpt-6-luna')['limit'] ?? null)->toBe(['context' => 400000, 'output' => 128000]);
    Http::assertSentCount(1);
});

it('gives no facts when the catalog cannot be read, and waits a day to try again', function (): void {
    Http::fake([ModelsDevCatalog::URL => Http::response('down', 503)]);
    $catalog = app(ModelsDevCatalog::class);

    expect($catalog->facts(LlmProviderKind::OpenAi, 'gpt-6-luna'))->toBeNull()
        ->and($catalog->facts(LlmProviderKind::OpenAi, 'gpt-4.1-mini'))->toBeNull();

    Http::assertSentCount(1);
});

it('reads nothing while the lookup is off', function (): void {
    config(['chatvia.ai.models_dev.enabled' => false]);
    Http::fake();

    expect(app(ModelsDevCatalog::class)->facts(LlmProviderKind::OpenAi, 'gpt-6-luna'))->toBeNull();

    Http::assertNothingSent();
});
