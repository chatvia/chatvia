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

it('gives a model its lab\'s logo, else its provider\'s', function (): void {
    Http::fake([ModelsDevCatalog::URL => Http::response([
        'providers' => [
            'openrouter' => ['models' => ['qwen/qwen3-235b-a22b' => ['canonical_model_id' => 'alibaba/qwen3-235b-a22b']]],
            'openai' => ['models' => ['gpt-4.1' => ['tool_call' => true]]],
        ],
        'models' => ['alibaba/qwen3-8b' => ['tool_call' => true]],
    ])]);
    $catalog = app(ModelsDevCatalog::class);
    $catalog->refresh();

    expect($catalog->logo(LlmProviderKind::OpenRouter, 'qwen/qwen3-235b-a22b'))->toBe('labs/alibaba')
        // Its provider made it: the catalog names no other lab.
        ->and($catalog->logo(LlmProviderKind::OpenAi, 'gpt-4.1'))->toBe('openai')
        // Not listed: its provider's.
        ->and($catalog->logo(LlmProviderKind::OpenRouter, 'someone/new-model'))->toBe('openrouter')
        // A server of your own: the lab of the catalog's general model, none when it is not listed.
        ->and($catalog->logo(LlmProviderKind::Custom, 'alibaba/qwen3-8b'))->toBe('labs/alibaba')
        ->and($catalog->logo(LlmProviderKind::Custom, 'llama3.1:8b'))->toBeNull()
        // A provider itself: its own logo, none for a server of your own.
        ->and($catalog->providerLogo(LlmProviderKind::OpenRouter))->toBe('openrouter')
        ->and($catalog->providerLogo(LlmProviderKind::Custom))->toBeNull();

    config(['chatvia.ai.models_dev.enabled' => false]);

    expect($catalog->logo(LlmProviderKind::OpenAi, 'gpt-4.1'))->toBeNull()
        ->and($catalog->providerLogo(LlmProviderKind::OpenAi))->toBeNull();
});

it('downloads a logo once and keeps it, and refuses anything but an SVG of a known shape', function (): void {
    Http::fake([
        'models.dev/logos/labs/alibaba.svg' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        'models.dev/logos/broken.svg' => Http::response('<html>not a logo</html>'),
    ]);
    $catalog = app(ModelsDevCatalog::class);

    expect($catalog->logoSvg('labs/alibaba'))->toBe('<svg xmlns="http://www.w3.org/2000/svg"></svg>')
        ->and($catalog->logoSvg('labs/alibaba'))->toStartWith('<svg')
        ->and($catalog->logoSvg('broken'))->toBeNull()
        ->and($catalog->logoSvg('../catalog'))->toBeNull()
        ->and($catalog->logoSvg('labs/a/b'))->toBeNull();

    Http::assertSentCount(2);
});

it('says a model reads images when an image is among its inputs, not when it only takes attachments', function (): void {
    expect(ModelFactsData::fromCatalog(['attachment' => true, 'modalities' => ['input' => ['text', 'image'], 'output' => ['text']]])->readsImages)->toBeTrue()
        // Takes files, but audio: it does not read images.
        ->and(ModelFactsData::fromCatalog(['attachment' => true, 'modalities' => ['input' => ['audio'], 'output' => ['text']]])->readsImages)->toBeFalse()
        ->and(ModelFactsData::fromCatalog(['attachment' => false])->readsImages)->toBeNull();
});
