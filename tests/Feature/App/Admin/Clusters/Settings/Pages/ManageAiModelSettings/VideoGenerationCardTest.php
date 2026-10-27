<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Contracts\VideoGenerator;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Operations\Media\Video\ElevenLabsVideoGenerator;
use Domain\Llm\Operations\Media\Video\FalVideoGenerator;
use Domain\Llm\Operations\Media\Video\GoogleVideoGenerator;
use Domain\Llm\Operations\Media\Video\KlingVideoGenerator;
use Domain\Llm\Operations\Media\Video\NullVideoGenerator;
use Domain\Llm\Operations\Media\Video\RunwayVideoGenerator;
use Domain\Llm\Operations\Media\Video\SeedanceVideoGenerator;
use Filament\Forms\Components\Select;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The Video generation card of Settings → AI models: off until an admin
 * picks a service (a connected key alone does not turn it on), the most
 * videos one chat gets in an hour, the length and resolution asked of every
 * video, and each service's model. Turning a service on checks its key with
 * a real call.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

it('stays off while a key is connected and nothing is picked', function (): void {
    connectAiProviders(AiProvider::Google, AiProvider::Runway);

    $page = adminPage(ManageAiModelSettings::class, $this->admin)->assertFormSet(['video_generator' => 'none']);
    /** @var Select $field */
    $field = $page->instance()->form->getFlatFields(withHidden: true)['video_generator'];

    expect(collect($field->getOptions())->map(fn (string $label): string => Str::of($label)->stripTags()->trim()->toString())->all())->toBe([
        'google' => 'Google Gemini (connected)',
        'elevenlabs' => 'ElevenLabs (not connected)',
        'kling' => 'Kling (not connected)',
        'byteplus' => 'BytePlus Seedance (not connected)',
        'runway' => 'Runway (connected)',
        'fal' => 'fal.ai (not connected)',
        'none' => 'Off',
    ])->and($field->isOptionDisabled('kling', 'Kling'))->toBeTrue()
        ->and(app(VideoGenerator::class))->toBeInstanceOf(NullVideoGenerator::class);
});

it('saves the service, its model, the hourly limit, length and resolution', function (): void {
    connectAiProviders(AiProvider::Runway);
    Http::fake(['api.dev.runwayml.com/v1/organization' => Http::response(['creditBalance' => 100])]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([
            'video_generator' => 'runway',
            'runway_video_model' => 'veo3.1_fast',
            'videos_per_chat_per_hour' => 3,
            'video_duration' => 6,
            'video_resolution' => '1080p',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('chatvia.ai.video_generator'))->toBe('runway')
        ->and(config('chatvia.ai.videos_per_chat_per_hour'))->toBe(3)
        ->and(config('chatvia.ai.video_duration'))->toBe(6)
        ->and(config('chatvia.ai.video_resolution'))->toBe('1080p')
        ->and(app(VideoGenerator::class)->name())->toBe('runway veo3.1_fast');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.dev.runwayml.com/v1/organization'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request->hasHeader('X-Runway-Version', RunwayVideoGenerator::API_VERSION));
});

it('refuses a service whose key the provider refuses', function (): void {
    connectAiProviders(AiProvider::Kling);
    Http::fake(['*/account/billing/balance' => Http::response(['code' => 1002, 'message' => 'Authorization is invalid'], 401)]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['video_generator' => 'kling'])
        ->call('save')
        ->assertHasFormErrors(['video_generator' => 'Kling refused the key. Check that you copied all of it.']);

    expect(savedSetting(AiSettings::class, 'video_generator'))->toBeNull();
});

it('picks each service by its saved name', function (string $driver, string $class): void {
    config(['chatvia.ai.video_generator' => $driver]);

    expect(app(VideoGenerator::class))->toBeInstanceOf($class);
})->with([
    'google' => ['google', GoogleVideoGenerator::class],
    'elevenlabs' => ['elevenlabs', ElevenLabsVideoGenerator::class],
    'kling' => ['kling', KlingVideoGenerator::class],
    'byteplus' => ['byteplus', SeedanceVideoGenerator::class],
    'runway' => ['runway', RunwayVideoGenerator::class],
    'fal' => ['fal', FalVideoGenerator::class],
    'off' => ['none', NullVideoGenerator::class],
    'automatic stays off' => ['auto', NullVideoGenerator::class],
]);

it('offers every model its generator knows', function (string $property, array $models): void {
    /** @var Select $field */
    $field = adminPage(ManageAiModelSettings::class, $this->admin)->instance()->form->getFlatFields(withHidden: true)[$property];

    expect(array_keys($field->getOptions()))->toEqualCanonicalizing(array_keys($models))
        ->and(array_key_first($field->getOptions()))->toBe(config(AiSettings::configKeys()[$property][0]->key));
})->with([
    'google' => ['google_video_model', GoogleVideoGenerator::MODELS],
    'elevenlabs' => ['elevenlabs_video_model', ElevenLabsVideoGenerator::MODELS],
    'kling' => ['kling_video_model', KlingVideoGenerator::MODELS],
    'byteplus' => ['byteplus_video_model', SeedanceVideoGenerator::MODELS],
    'runway' => ['runway_video_model', RunwayVideoGenerator::MODELS],
    'fal' => ['fal_video_model', FalVideoGenerator::MODELS],
]);

it('refuses an hourly limit or a length out of range', function (string $property, int $value): void {
    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([$property => $value])
        ->call('save')
        ->assertHasFormErrors([$property]);
})->with([
    'no videos' => ['videos_per_chat_per_hour', 0],
    'too long' => ['video_duration', 30],
]);

it('sums up the service, model, length and resolution in use', function (): void {
    saveSettings(AiSettings::class, ['video_generator' => 'fal', 'fal_video_model' => 'fal-ai/veo3.1/fast', 'video_duration' => 6]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertSee('fal.ai · fal-ai/veo3.1/fast · 6 s, 720p');
});
