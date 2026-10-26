<?php

declare(strict_types=1);

namespace Domain\Llm\Contracts;

use Domain\Llm\Data\Image\GeneratedImageData;
use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Domain\Llm\Exceptions\ImageGenerationNotConfiguredException;

/**
 * Makes one picture from a text prompt, for agents that draw in the hosted
 * chat. The implementation is chosen from config (`chatvia.ai.image_generator`),
 * and it is off until an admin picks a service: a connected key alone does not
 * turn it on.
 */
interface ImageGenerator
{
    /**
     * @throws ImageGenerationNotConfiguredException When no image service is set up.
     * @throws ImageGenerationFailedException When the service failed or refused the prompt.
     */
    public function generate(ImagePromptData $prompt): GeneratedImageData;

    /** Whether an image service is set up (Settings → AI models → Image generation). */
    public function isEnabled(): bool;

    /** The service and model that draw, stored with each picture made. */
    public function name(): string;
}
