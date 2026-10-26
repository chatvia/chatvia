<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Image;

use Domain\Llm\Enums\ImageShape;
use Spatie\LaravelData\Data;

/**
 * What to draw: the prompt the agent wrote, and the shape of the picture.
 */
class ImagePromptData extends Data
{
    public function __construct(
        public string $prompt,
        public ImageShape $shape = ImageShape::Square,
    ) {}
}
