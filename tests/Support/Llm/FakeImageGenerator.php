<?php

declare(strict_types=1);

namespace Tests\Support\Llm;

use Domain\Llm\Contracts\ImageGenerator;
use Domain\Llm\Data\Image\GeneratedImageData;
use Domain\Llm\Data\Image\ImagePromptData;
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
        private readonly bool $enabled = true,
    ) {}

    public function generate(ImagePromptData $prompt): GeneratedImageData
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        $this->drawn[] = $prompt;

        return new GeneratedImageData("[{$prompt->shape->value}:{$prompt->prompt}]", 'image/png');
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function name(): string
    {
        return 'fake image-model';
    }

    /** Bind it as the image service, and return it to check what it drew. */
    public static function install(?Throwable $failure = null, bool $enabled = true): self
    {
        $fake = new self($failure, $enabled);
        app()->instance(ImageGenerator::class, $fake);

        return $fake;
    }
}
