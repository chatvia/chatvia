<?php

use App\Admin\Clusters\Settings\Components\MediaModelsTable;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Services\MediaModelCatalog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * The Image generation card of Settings → AI models: a table of image
 * models from one service or several. A service new to the table is checked
 * with a real call before its models are added, once per service.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

it('adds models of two services, checking each service once as it joins', function (): void {
    connectAiProviders(AiProvider::OpenAi, AiProvider::Fal);
    Http::fake([
        'api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-image-2', 'object' => 'model']]]),
        'queue.fal.run/*' => Http::response(['detail' => 'Request not found'], 404),
    ]);

    Livewire::actingAs($this->admin, 'admin')->test(MediaModelsTable::class, ['kind' => 'image'])
        ->callAction(TestAction::make('addMediaModel')->table(), data: ['service' => 'openai', 'models' => ['gpt-image-2']])
        ->assertHasNoActionErrors()
        ->tap(fn () => connectAiProviders(AiProvider::OpenAi, AiProvider::Fal))
        ->callAction(TestAction::make('addMediaModel')->table(), data: ['service' => 'fal', 'models' => ['fal-ai/flux-2', 'fal-ai/qwen-image']])
        ->assertHasNoActionErrors()
        ->tap(fn () => connectAiProviders(AiProvider::OpenAi, AiProvider::Fal))
        ->callAction(TestAction::make('addMediaModel')->table(), data: ['service' => 'openai', 'models' => ['gpt-image-1-mini']])
        ->assertHasNoActionErrors();

    expect(app(AiSettings::class)->refresh())
        ->image_models->toBe(['openai:gpt-image-2', 'fal:fal-ai/flux-2', 'fal:fal-ai/qwen-image', 'openai:gpt-image-1-mini'])
        ->default_image_model->toBe('openai:gpt-image-2')
        ->and(app(MediaModelCatalog::class)->imageEnabled())->toBeTrue();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/models'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});
