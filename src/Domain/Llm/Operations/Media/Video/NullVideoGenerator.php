<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media\Video;

use Domain\Llm\Contracts\VideoGenerator;
use Domain\Llm\Data\Video\GeneratedVideoData;
use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Exceptions\VideoGenerationNotConfiguredException;

/**
 * Used while no video service is picked, the default. Agents cannot turn
 * video generation on then, and a call still fails with a clear "not set
 * up" error.
 */
class NullVideoGenerator implements VideoGenerator
{
    public function start(VideoPromptData $prompt): string
    {
        throw new VideoGenerationNotConfiguredException;
    }

    public function check(string $taskId): ?GeneratedVideoData
    {
        throw new VideoGenerationNotConfiguredException;
    }

    public function forget(string $taskId): void {}

    public function isEnabled(): bool
    {
        return false;
    }

    public function name(?string $model = null): string
    {
        return 'none';
    }
}
