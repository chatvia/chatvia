<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\ImageGenerator;
use Domain\Llm\Data\Image\GeneratedImageData;
use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Exceptions\ImageGenerationNotConfiguredException;

/**
 * Used while no image service is picked, the default. Agents cannot turn
 * image generation on then, and a call still fails with a clear "not set up"
 * error.
 */
class NullImageGenerator implements ImageGenerator
{
    public function generate(ImagePromptData $prompt): GeneratedImageData
    {
        throw new ImageGenerationNotConfiguredException;
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function name(?string $model = null): string
    {
        return 'none';
    }
}
