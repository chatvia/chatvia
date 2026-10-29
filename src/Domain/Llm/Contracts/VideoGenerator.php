<?php

declare(strict_types=1);

namespace Domain\Llm\Contracts;

use Domain\Llm\Data\Video\GeneratedVideoData;
use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Services\MediaGenerators;

/**
 * Makes one short video from a text prompt, for agents in the hosted chat.
 * Every video service takes minutes, so a video is started, then checked
 * from time to time until it is made: the task id {@see start()} returns is
 * all {@see check()} needs. Each video service has one;
 * {@see MediaGenerators} gives the one that runs the model in use, and the
 * one that started a video checks on it.
 */
interface VideoGenerator
{
    /**
     * Asks the service for the video and returns its task id.
     *
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
}
