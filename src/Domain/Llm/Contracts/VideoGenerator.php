<?php

declare(strict_types=1);

namespace Domain\Llm\Contracts;

use Domain\Llm\Data\Video\GeneratedVideoData;
use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Exceptions\VideoGenerationNotConfiguredException;

/**
 * Makes one short video from a text prompt, for agents in the hosted chat.
 * Every video service takes minutes, so a video is started, then checked
 * from time to time until it is made: the task id {@see start()} returns is
 * all {@see check()} needs. The implementation is chosen from config
 * (`chatvia.ai.video_generator`), and it is off until an admin picks a
 * service: a connected key alone does not turn it on.
 */
interface VideoGenerator
{
    /**
     * Asks the service for the video and returns its task id.
     *
     * @throws VideoGenerationNotConfiguredException When no video service is set up.
     * @throws VideoGenerationFailedException When the service failed or refused the prompt.
     */
    public function start(VideoPromptData $prompt): string;

    /**
     * The video, saved to a temp file the caller owns, once it is made; null
     * while the service is still making it.
     *
     * @throws VideoGenerationFailedException When the service failed or refused the prompt.
     */
    public function check(string $taskId): ?GeneratedVideoData;

    /** Deletes the service's copy of a made video where its API allows; never fails. */
    public function forget(string $taskId): void;

    /** Whether a video service is set up (Settings → AI models → Video generation). */
    public function isEnabled(): bool;

    /** The service and model that make videos (the admin's, or the one given), stored with each video. */
    public function name(?string $model = null): string;
}
