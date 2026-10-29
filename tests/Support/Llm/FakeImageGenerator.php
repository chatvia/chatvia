<?php

declare(strict_types=1);

namespace Tests\Support\Llm;

use Domain\Llm\Contracts\ImageGenerator;
use Domain\Llm\Data\Image\GeneratedImageData;
use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Data\Media\MediaModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Operations\Media\FalImageGenerator;
use Domain\Llm\Operations\Media\GoogleImageGenerator;
use Domain\Llm\Operations\Media\OpenAiImageGenerator;
use Domain\Llm\Operations\Media\OpenRouterImageGenerator;
use Throwable;

/**
 * Offline stand-in for an image service: it is set up, draws each prompt as
 * the PNG bytes "[shape:prompt]", and remembers what it drew. Given a
 * failure, every call throws it.
 */
final class FakeImageGenerator implements ImageGenerator
{
    /** @var list<ImagePromptData> */
    public array $drawn = [];

    public function __construct(
        private readonly ?Throwable $failure = null,
    ) {}

    public function generate(ImagePromptData $prompt): GeneratedImageData
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        $this->drawn[] = $prompt;

        return new GeneratedImageData("[{$prompt->shape->value}:{$prompt->prompt}]", 'image/png');
    }

    /**
     * Bind it as the generator of one image service, and list that
     * service's model (none when $enabled is false), so agents can draw;
     * return it to check what it drew.
     */
    public static function install(?Throwable $failure = null, bool $enabled = true, AiProvider $service = AiProvider::Fal, string $model = 'fal-ai/flux-2'): self
    {
        $fake = new self($failure);
        app()->instance(match ($service) {
            AiProvider::OpenAi => OpenAiImageGenerator::class,
            AiProvider::OpenRouter => OpenRouterImageGenerator::class,
            AiProvider::Google => GoogleImageGenerator::class,
            default => FalImageGenerator::class,
        }, $fake);
        config(['chatvia.ai.image_models' => $enabled ? [MediaModelData::keyOf($service, $model)] : []]);

        return $fake;
    }
}
