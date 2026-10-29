<?php

declare(strict_types=1);

namespace Domain\Llm\Contracts;

use Domain\Llm\Data\Image\GeneratedImageData;
use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Domain\Llm\Services\MediaGenerators;

/**
 * Makes one picture from a text prompt, for agents that draw in the hosted
 * chat. Each image service has one; {@see MediaGenerators} gives the one
 * that runs the model in use, from the models the admin listed.
 */
interface ImageGenerator
{
    /**
     * @throws ImageGenerationFailedException When the service failed or refused the prompt.
     */
    public function generate(ImagePromptData $prompt): GeneratedImageData;
}
