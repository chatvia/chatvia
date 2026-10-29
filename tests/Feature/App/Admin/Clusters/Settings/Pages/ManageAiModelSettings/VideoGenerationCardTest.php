<?php

use App\Admin\Clusters\Settings\Components\MediaModelsTable;
use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Operations\Media\Video\RunwayVideoGenerator;
use Domain\Llm\Services\MediaModelCatalog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * The Video generation card of Settings → AI models: a table of video
 * models, from one service or several, off while it is empty; the default
 * model; the most videos one chat gets in an hour; and the length and
 * resolution asked of every video. A service new to the table is checked
 * with a real call before its models are added.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

function vgcTable(): mixed
{
    return Livewire::actingAs(test()->admin, 'admin')->test(MediaModelsTable::class, ['kind' => 'video']);
}

it('adds a service\'s models once the service accepts the key', function (): void {
    connectAiProviders(AiProvider::Runway);
    Http::fake(['api.dev.runwayml.com/v1/organization' => Http::response(['creditBalance' => 100])]);

    vgcTable()
        ->callAction(TestAction::make('addMediaModel')->table(), data: ['service' => 'runway', 'models' => ['veo3.1_fast', 'gen4.5']])
        ->assertHasNoActionErrors()
        ->assertNotified('Models added');

    expect(collect(app(MediaModelCatalog::class)->videoModels())->map->key()->all())->toBe(['runway:veo3.1_fast', 'runway:gen4.5'])
        ->and(app(MediaModelCatalog::class)->defaultVideo()?->key())->toBe('runway:veo3.1_fast');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.dev.runwayml.com/v1/organization'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request->hasHeader('X-Runway-Version', RunwayVideoGenerator::API_VERSION));
});

it('refuses a service whose key the provider refuses', function (): void {
    connectAiProviders(AiProvider::Kling);
    Http::fake(['*/account/billing/balance' => Http::response(['code' => 1002, 'message' => 'Authorization is invalid'], 401)]);

    vgcTable()
        ->callAction(TestAction::make('addMediaModel')->table(), data: ['service' => 'kling', 'models' => ['kling-4.0']])
        ->assertNotified('Kling did not answer');

    expect(savedSetting(AiSettings::class, 'video_models'))->toBeNull();
});

it('saves the default, the hourly limit, length and resolution', function (): void {
    saveSettings(AiSettings::class, ['video_models' => ['kling:kling-3.0-turbo', 'fal:fal-ai/veo3.1/fast']]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([
            'default_video_model' => 'fal:fal-ai/veo3.1/fast',
            'videos_per_chat_per_hour' => 3,
            'video_duration' => 6,
            'video_resolution' => '1080p',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(MediaModelCatalog::class)->defaultVideo()?->key())->toBe('fal:fal-ai/veo3.1/fast')
        ->and(config('chatvia.ai.videos_per_chat_per_hour'))->toBe(3)
        ->and(config('chatvia.ai.video_duration'))->toBe(6)
        ->and(config('chatvia.ai.video_resolution'))->toBe('1080p');
});

it('refuses a default that is not on the list', function (): void {
    saveSettings(AiSettings::class, ['video_models' => ['kling:kling-3.0-turbo']]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['default_video_model' => 'runway:gen4.5'])
        ->call('save')
        ->assertHasFormErrors(['default_video_model']);
});

it('refuses an hourly limit or a length out of range', function (string $property, int $value): void {
    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([$property => $value])
        ->call('save')
        ->assertHasFormErrors([$property]);
})->with([
    'no videos' => ['videos_per_chat_per_hour', 0],
    'too long' => ['video_duration', 30],
]);

it('sums up the models, the default, length and resolution', function (): void {
    saveSettings(AiSettings::class, ['video_models' => ['fal:fal-ai/veo3.1/fast'], 'video_duration' => 6]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertSee('1 model · fal.ai Google Veo 3.1 Fast · 6 s, 720p');
});
