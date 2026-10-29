<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Contracts\ImageGenerator;
use Domain\Llm\Contracts\VideoGenerator;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Exceptions\ImageGenerationNotConfiguredException;
use Domain\Llm\Exceptions\VideoGenerationNotConfiguredException;
use Domain\Llm\Operations\Media\FalImageGenerator;
use Domain\Llm\Operations\Media\GoogleImageGenerator;
use Domain\Llm\Operations\Media\OpenAiImageGenerator;
use Domain\Llm\Operations\Media\OpenRouterImageGenerator;
use Domain\Llm\Operations\Media\Video\ElevenLabsVideoGenerator;
use Domain\Llm\Operations\Media\Video\FalVideoGenerator;
use Domain\Llm\Operations\Media\Video\GoogleVideoGenerator;
use Domain\Llm\Operations\Media\Video\KlingVideoGenerator;
use Domain\Llm\Operations\Media\Video\RunwayVideoGenerator;
use Domain\Llm\Operations\Media\Video\SeedanceVideoGenerator;
use Illuminate\Contracts\Container\Container;

/**
 * The image or video generator of one service. The admin may list models of
 * several services at once (Settings → AI models), so the service is picked
 * per item: the one of the model in use, kept on the item, so a video that
 * is still being made is checked by the service that started it.
 */
final readonly class MediaGenerators
{
    public function __construct(
        private Container $container,
    ) {}

    /**
     * @throws ImageGenerationNotConfiguredException When the service makes no pictures.
     */
    public function image(?AiProvider $service): ImageGenerator
    {
        return match ($service) {
            AiProvider::OpenAi => $this->container->make(OpenAiImageGenerator::class),
            AiProvider::OpenRouter => $this->container->make(OpenRouterImageGenerator::class),
            AiProvider::Google => $this->container->make(GoogleImageGenerator::class),
            AiProvider::Fal => $this->container->make(FalImageGenerator::class),
            default => throw new ImageGenerationNotConfiguredException,
        };
    }

    /**
     * @throws VideoGenerationNotConfiguredException When the service makes no videos.
     */
    public function video(?AiProvider $service): VideoGenerator
    {
        return match ($service) {
            AiProvider::Google => $this->container->make(GoogleVideoGenerator::class),
            AiProvider::ElevenLabs => $this->container->make(ElevenLabsVideoGenerator::class),
            AiProvider::Kling => $this->container->make(KlingVideoGenerator::class),
            AiProvider::BytePlus => $this->container->make(SeedanceVideoGenerator::class),
            AiProvider::Runway => $this->container->make(RunwayVideoGenerator::class),
            AiProvider::Fal => $this->container->make(FalVideoGenerator::class),
            default => throw new VideoGenerationNotConfiguredException,
        };
    }
}
