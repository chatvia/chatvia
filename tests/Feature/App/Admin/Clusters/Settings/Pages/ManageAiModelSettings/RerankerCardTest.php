<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The Reranker card of Settings → AI models: a new reranker model is tested
 * with a short rerank as it is saved, and what is saved is the reranker the
 * search and tool routing use.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    app()->forgetInstance(Reranker::class);

    config([
        'chatvia.ai.reranker' => 'auto',
        AiProvider::OpenRouter->apiKeyConfig() => null,
        AiProvider::Cohere->apiKeyConfig() => null,
    ]);
});

it('tests a new OpenRouter model with a rerank before it saves it, and the search uses it', function (): void {
    connectAiProviders(AiProvider::OpenRouter);
    Http::fake(['openrouter.ai/api/v1/rerank' => Http::response(['results' => [['index' => 0, 'relevance_score' => 0.9], ['index' => 1, 'relevance_score' => 0.1]]])]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['reranker' => 'openrouter', 'openrouter_rerank_model' => 'qwen/qwen3-reranker-8b'])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetScopedInstances();

    expect(app(AiSettings::class)->openrouter_rerank_model)->toBe('qwen/qwen3-reranker-8b')
        ->and(app(Reranker::class)->model())->toBe('qwen/qwen3-reranker-8b');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/rerank'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['model'] === 'qwen/qwen3-reranker-8b');
});
