<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Contracts\ImageGenerator;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Operations\Media\FalImageGenerator;
use Domain\Llm\Operations\Media\GoogleImageGenerator;
use Domain\Llm\Operations\Media\NullImageGenerator;
use Domain\Llm\Operations\Media\OpenAiImageGenerator;
use Domain\Llm\Operations\Media\OpenRouterImageGenerator;
use Filament\Forms\Components\Select;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The Image generation card of Settings → AI models: off until an admin
 * picks a service (a connected key alone does not turn it on), the models,
 * and the most pictures one chat gets in an hour. Turning a service on
 * checks its key with a real call. The Read aloud card holds the hourly
 * limit on audio files, which the same speech service makes. What the
 * pickers share is in ServiceChoicesTest.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

it('stays off while a key is connected and nothing is picked', function (): void {
    connectAiProviders(AiProvider::OpenAi, AiProvider::Google);

    $page = adminPage(ManageAiModelSettings::class, $this->admin)->assertFormSet(['image_generator' => 'none']);
    /** @var Select $field */
    $field = $page->instance()->form->getFlatFields(withHidden: true)['image_generator'];

    expect(collect($field->getOptions())->map(fn (string $label): string => Str::of($label)->stripTags()->trim()->toString())->all())->toBe([
        'openai' => 'OpenAI GPT Image (connected)',
        'openrouter' => 'OpenRouter (not connected)',
        'google' => 'Google Gemini (connected)',
        'fal' => 'fal.ai (not connected)',
        'none' => 'Off',
    ])->and($field->isOptionDisabled('openrouter', 'OpenRouter'))->toBeTrue()
        ->and(app(ImageGenerator::class))->toBeInstanceOf(NullImageGenerator::class);
});

it('saves the service, its model and the hourly limits', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    Http::fake(['api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-image-2.5-flare', 'object' => 'model']]])]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([
            'image_generator' => 'openai',
            'openai_image_model' => 'gpt-image-2',
            'images_per_chat_per_hour' => 4,
            'audio_per_chat_per_hour' => 6,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('chatvia.ai.image_generator'))->toBe('openai')
        ->and(config('chatvia.ai.images_per_chat_per_hour'))->toBe(4)
        ->and(config('chatvia.ai.audio_per_chat_per_hour'))->toBe(6)
        ->and(app(ImageGenerator::class))->toBeInstanceOf(OpenAiImageGenerator::class)
        ->and(app(ImageGenerator::class)->name())->toBe('openai gpt-image-2');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/models'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('picks each service by its saved name', function (string $driver, string $class): void {
    config(['chatvia.ai.image_generator' => $driver]);

    expect(app(ImageGenerator::class))->toBeInstanceOf($class);
})->with([
    'openai' => ['openai', OpenAiImageGenerator::class],
    'openrouter' => ['openrouter', OpenRouterImageGenerator::class],
    'google' => ['google', GoogleImageGenerator::class],
    'fal' => ['fal', FalImageGenerator::class],
    'off' => ['none', NullImageGenerator::class],
    'automatic stays off' => ['auto', NullImageGenerator::class],
]);

it('refuses a service whose key the provider refuses', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    Http::fake(['api.openai.com/v1/models' => Http::response(['error' => ['message' => 'Incorrect API key provided']], 401)]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['image_generator' => 'openai'])
        ->call('save')
        ->assertHasFormErrors(['image_generator' => 'OpenAI refused the API key. Check that you copied all of it.']);

    expect(savedSetting(AiSettings::class, 'image_generator'))->toBeNull();
});

it('refuses an hourly limit out of range', function (string $property): void {
    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([$property => 0])
        ->call('save')
        ->assertHasFormErrors([$property]);
})->with(['images_per_chat_per_hour', 'audio_per_chat_per_hour']);

it('offers the OpenRouter models whose output includes images', function (): void {
    Http::fake(['openrouter.ai/api/v1/models*' => Http::response(['data' => [
        ['id' => 'black-forest-labs/flux-3-image', 'name' => 'FLUX 3', 'architecture' => ['output_modalities' => ['image']]],
    ]])]);

    /** @var Select $field */
    $field = adminPage(ManageAiModelSettings::class, $this->admin)->instance()->form->getFlatFields(withHidden: true)['openrouter_image_model'];

    expect(array_keys($field->getOptions()))->toContain('google/gemini-nano-banana-2.1', 'black-forest-labs/flux-3-image');
});

it('sums up the service and model in use', function (): void {
    saveSettings(AiSettings::class, ['image_generator' => 'google', 'google_image_model' => 'gemini-3-pro-image']);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertSee('Google Gemini · gemini-3-pro-image');
});

it('turns fal.ai on once fal.ai accepts the key', function (): void {
    connectAiProviders(AiProvider::Fal);
    Http::fake(['queue.fal.run/*' => Http::response(['detail' => 'Request not found'], 404)]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['image_generator' => 'fal', 'fal_image_model' => 'fal-ai/qwen-image'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(ImageGenerator::class)->name())->toBe('fal fal-ai/qwen-image');

    Http::assertSent(fn (Request $request): bool => Str::startsWith($request->url(), 'https://queue.fal.run/')
        && $request->hasHeader('Authorization', 'Key test-key'));
});

it('offers every fal.ai model the generator knows how to shape', function (): void {
    /** @var Select $field */
    $field = adminPage(ManageAiModelSettings::class, $this->admin)->instance()->form->getFlatFields(withHidden: true)['fal_image_model'];

    expect(array_keys($field->getOptions()))->toBe(array_keys(FalImageGenerator::MODELS));
});
