<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Image;

use Domain\Llm\Enums\ImageShape;
use Spatie\LaravelData\Data;

/**
 * What to draw: the prompt the agent wrote, the shape of the picture, and
 * the model the chatter picked (null: the admin's).
 */
class ImagePromptData extends Data
{
    public function __construct(
        public string $prompt,
        public ImageShape $shape = ImageShape::Square,
        public ?string $model = null,
    ) {}
}
