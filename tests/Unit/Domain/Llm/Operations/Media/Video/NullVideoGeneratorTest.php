<?php

use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Exceptions\VideoGenerationNotConfiguredException;
use Domain\Llm\Operations\Media\Video\NullVideoGenerator;

/**
 * Without a video service, video generation is off and every call fails
 * with a plain "not set up" error.
 */
it('is off and refuses to make a video', function (): void {
    $generator = new NullVideoGenerator;

    expect($generator->isEnabled())->toBeFalse()
        ->and($generator->name())->toBe('none')
        ->and(fn () => $generator->start(new VideoPromptData('A wave')))->toThrow(VideoGenerationNotConfiguredException::class)
        ->and(fn () => $generator->check('task'))->toThrow(VideoGenerationNotConfiguredException::class);
});
