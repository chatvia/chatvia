<?php

declare(strict_types=1);

use Domain\Llm\Data\Catalog\ModelFactsData;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The models.dev catalog gives each model's facts: whether it reasons, the
 * effort values it takes, whether it takes a temperature, calls tools and
 * keeps to a JSON schema, its longest reply. It is downloaded at most once a
 * day and only when it changed (ETag). Logos are taken only as SVGs of a
 * known path. The entries below follow the catalog's shape.
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
    forgetModelsDevCatalog();
});

it('gives each model the facts its provider lists, and downloads the catalog again only when it changed', function (): void {
    Http::fakeSequence()
        ->push(mdCatalog(), 200, ['ETag' => '"v1"'])
        ->push('', 304);
    $catalog = app(ModelsDevCatalog::class);

    expect($catalog->facts(LlmProviderKind::OpenAi, 'gpt-6-luna'))->toEqual(new ModelFactsData(true, ['none', 'low', 'medium', 'high', 'xhigh', 'max'], false, 128000, callsTools: true, takesResponseSchema: true))
        ->and($catalog->facts(LlmProviderKind::OpenAi, 'gpt-4.1-mini'))->toEqual(new ModelFactsData(false, null, true))
        // Reasons without named effort values: no values to send.
        ->and($catalog->facts(LlmProviderKind::OpenRouter, 'anthropic/claude-x'))->toEqual(new ModelFactsData(true, null, true))
        // A server of your own: the catalog's general models, by id.
        ->and($catalog->facts(LlmProviderKind::Custom, 'qwen/qwen3-8b')?->efforts)->toBe(['none', 'low', 'medium', 'high'])
        ->and($catalog->facts(LlmProviderKind::OpenAi, 'not-listed'))->toBeNull();

    Http::assertSentCount(1);

    $this->travel(25)->hours();

    expect($catalog->facts(LlmProviderKind::OpenAi, 'gpt-6-luna')?->reasoning)->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->header('If-None-Match') === ['"v1"']);
    Http::assertSentCount(2);
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
