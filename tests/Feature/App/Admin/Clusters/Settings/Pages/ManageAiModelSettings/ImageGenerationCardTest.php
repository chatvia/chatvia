<?php

use App\Admin\Clusters\Settings\Components\MediaModelsTable;
use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Forms\ModelPicker;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Services\MediaModelCatalog;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * The Image generation card of Settings → AI models: a table of image
 * models like the Chat card's, from one service or several, off while it is
 * empty (a connected key alone does not turn it on); the default model; and
 * the most pictures one chat gets in an hour. A service new to the table is
 * checked with a real call before its models are added. The Read aloud card
 * holds the hourly limit on audio files.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

function igcTable(): mixed
{
    return Livewire::actingAs(test()->admin, 'admin')->test(MediaModelsTable::class, ['kind' => 'image']);
}

it('stays off while a key is connected and no model is listed', function (): void {
    connectAiProviders(AiProvider::OpenAi, AiProvider::Google);

    adminPage(ManageAiModelSettings::class, $this->admin)->assertFormFieldHidden('default_image_model');

    igcTable()->assertSee('No image models yet');

    expect(app(MediaModelCatalog::class)->imageEnabled())->toBeFalse();
});

it('adds models of two services, checking each service once as it joins', function (): void {
    connectAiProviders(AiProvider::OpenAi, AiProvider::Fal);
    Http::fake([
        'api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-image-2', 'object' => 'model']]]),
        'queue.fal.run/*' => Http::response(['detail' => 'Request not found'], 404),
    ]);

    igcTable()
        ->callAction(TestAction::make('addMediaModel')->table(), data: ['service' => 'openai', 'models' => ['gpt-image-2']])
        ->assertHasNoActionErrors()
        ->assertNotified('Model added')
        // Saving reloads the settings into config, which holds no test keys.
        ->tap(fn () => connectAiProviders(AiProvider::OpenAi, AiProvider::Fal))
        ->callAction(TestAction::make('addMediaModel')->table(), data: ['service' => 'fal', 'models' => ['fal-ai/flux-2', 'fal-ai/qwen-image']])
        ->assertHasNoActionErrors()
        ->tap(fn () => connectAiProviders(AiProvider::OpenAi, AiProvider::Fal))
        ->callAction(TestAction::make('addMediaModel')->table(), data: ['service' => 'openai', 'models' => ['gpt-image-1-mini']])
        ->assertHasNoActionErrors()
        ->assertDispatched('media-models-changed');

    expect(app(AiSettings::class)->refresh())
        ->image_models->toBe(['openai:gpt-image-2', 'fal:fal-ai/flux-2', 'fal:fal-ai/qwen-image', 'openai:gpt-image-1-mini'])
        ->default_image_model->toBe('openai:gpt-image-2')
        ->and(app(MediaModelCatalog::class)->imageEnabled())->toBeTrue();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/models'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('refuses a service whose key the provider refuses', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    Http::fake(['api.openai.com/v1/models' => Http::response(['error' => ['message' => 'Incorrect API key provided']], 401)]);

    igcTable()
        ->callAction(TestAction::make('addMediaModel')->table(), data: ['service' => 'openai', 'models' => ['gpt-image-2']])
        ->assertNotified('OpenAI did not answer');

    expect(savedSetting(AiSettings::class, 'image_models'))->toBeNull();
});

it('lists a service\'s models to add, with the ones listed already locked', function (): void {
    saveSettings(AiSettings::class, ['image_models' => ['fal:fal-ai/flux-2']]);
    connectAiProviders(AiProvider::Fal);

    $table = igcTable()
        ->mountAction(TestAction::make('addMediaModel')->table())
        ->assertSchemaStateSet(['service' => 'fal'], 'mountedActionSchema0')
        ->assertMountedActionModalSee(['FLUX.2 pro', 'Prices differ between fal.ai models']);
    /** @var ModelPicker $picker */
    $picker = $table->instance()->getSchema('mountedActionSchema0')->getFlatFields()['models'];

    expect($picker->isOptionDisabled('fal-ai/flux-2', 'FLUX.2 dev'))->toBeTrue()
        ->and($picker->isOptionDisabled('fal-ai/flux-2-pro', 'FLUX.2 pro'))->toBeFalse();
});

it('makes the next model the default when the default is removed, and turns off with the last', function (): void {
    saveSettings(AiSettings::class, ['image_models' => ['fal:fal-ai/flux-2', 'openai:gpt-image-2'], 'default_image_model' => 'fal:fal-ai/flux-2']);

    $table = igcTable()->callAction(TestAction::make('removeMediaModel')->table('fal:fal-ai/flux-2'));

    expect(app(AiSettings::class)->refresh())
        ->image_models->toBe(['openai:gpt-image-2'])
        ->default_image_model->toBe('openai:gpt-image-2');

    $table->callAction(TestAction::make('removeMediaModel')->table('openai:gpt-image-2'));

    expect(app(AiSettings::class)->refresh()->image_models)->toBe([])
        ->and(app(MediaModelCatalog::class)->imageEnabled())->toBeFalse();
});

it('offers the listed models as the default, and sums them up', function (): void {
    saveSettings(AiSettings::class, ['image_models' => ['google:gemini-3-pro-image', 'fal:fal-ai/flux-2']]);

    $page = adminPage(ManageAiModelSettings::class, $this->admin)->assertSee('2 models · Google Gemini Nano Banana Pro');
    /** @var Select $field */
    $field = $page->instance()->form->getFlatFields(withHidden: true)['default_image_model'];

    expect(array_keys($field->getOptions()))->toBe(['google:gemini-3-pro-image', 'fal:fal-ai/flux-2']);

    $page->fillForm(['default_image_model' => 'fal:fal-ai/flux-2', 'images_per_chat_per_hour' => 4])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(MediaModelCatalog::class)->defaultImage()?->key())->toBe('fal:fal-ai/flux-2')
        ->and(config('chatvia.ai.images_per_chat_per_hour'))->toBe(4);
});

it('refuses an hourly limit out of range', function (string $property): void {
    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([$property => 0])
        ->call('save')
        ->assertHasFormErrors([$property]);
})->with(['images_per_chat_per_hour', 'audio_per_chat_per_hour']);
