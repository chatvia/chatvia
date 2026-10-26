<?php

use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Exceptions\ImageGenerationNotConfiguredException;
use Domain\Llm\Operations\Media\NullImageGenerator;

/**
 * No image service: off, and drawing fails with the "not set up" error.
 */
it('is off', function (): void {
    $generator = new NullImageGenerator;

    expect($generator->isEnabled())->toBeFalse()
        ->and($generator->name())->toBe('none');
});

it('refuses to draw', function (): void {
    (new NullImageGenerator)->generate(new ImagePromptData('A fox'));
})->throws(ImageGenerationNotConfiguredException::class);
