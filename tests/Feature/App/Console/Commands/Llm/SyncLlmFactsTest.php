<?php

declare(strict_types=1);

use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Support\Facades\Http;

/**
 * `chatvia:llm:sync-facts` runs the models.dev sync (SyncLlmFactsAction,
 * tested there) and says how many models it updated.
 */
it('syncs the model facts from the command line', function (): void {
    forgetModelsDevCatalog();
    Http::fake([ModelsDevCatalog::URL => Http::response(['providers' => ['openai' => ['models' => [
        'gpt-6-luna' => ['reasoning' => true, 'temperature' => false, 'reasoning_options' => [['type' => 'effort', 'values' => ['none', 'low', 'high']]]],
    ]]], 'models' => []]), '*' => Http::response(['choices' => []])]);
    testLlm('gpt-6-luna', LlmProviderKind::OpenAi);

    $this->artisan('chatvia:llm:sync-facts')->expectsOutputToContain('Updated 1 models')->assertSuccessful();
});
