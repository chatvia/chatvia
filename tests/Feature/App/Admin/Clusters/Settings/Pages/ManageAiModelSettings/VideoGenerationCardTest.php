<?php

use App\Admin\Clusters\Settings\Components\MediaModelsTable;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Operations\Media\Video\RunwayVideoGenerator;
use Domain\Llm\Services\MediaModelCatalog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * The Video generation card of Settings → AI models: a service's video
 * models are added to the table once a real call shows the service accepts
 * the key, and the first one becomes the default.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

it('adds a service\'s models once the service accepts the key', function (): void {
    connectAiProviders(AiProvider::Runway);
    Http::fake(['api.dev.runwayml.com/v1/organization' => Http::response(['creditBalance' => 100])]);

    Livewire::actingAs($this->admin, 'admin')->test(MediaModelsTable::class, ['kind' => 'video'])
        ->callAction(TestAction::make('addMediaModel')->table(), data: ['service' => 'runway', 'models' => ['veo3.1_fast', 'gen4.5']])
        ->assertHasNoActionErrors();

    expect(collect(app(MediaModelCatalog::class)->videoModels())->map->key()->all())->toBe(['runway:veo3.1_fast', 'runway:gen4.5'])
        ->and(app(MediaModelCatalog::class)->defaultVideo()?->key())->toBe('runway:veo3.1_fast');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.dev.runwayml.com/v1/organization'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request->hasHeader('X-Runway-Version', RunwayVideoGenerator::API_VERSION));
});
