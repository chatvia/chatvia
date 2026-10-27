<?php

declare(strict_types=1);

namespace Domain\Conversation\Jobs;

use Domain\Conversation\Actions\Media\CheckGeneratedVideoAction;
use Domain\Conversation\Actions\Media\FailGeneratedMediaAction;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

/**
 * Checks once whether a started video is made, on the `generation` queue
 * like {@see GenerateMediaJob}. The action dispatches the next check while
 * the video is not ready, so no worker sleeps while a video is made.
 */
#[Queue('generation')]
// One try: the action itself schedules the next check.
#[Tries(1)]
// Fetching a made video takes up to 180 seconds; below the pool's 300.
#[Timeout(240)]
// The conversation, and with it the item, may be deleted while the video is made.
#[DeleteWhenMissingModels]
class CheckGeneratedVideoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly GeneratedMedia $media,
    ) {}

    public function handle(CheckGeneratedVideoAction $action): void
    {
        $action->execute($this->media);
    }

    /** The check ran out of time or crashed: the chat must not wait for good. */
    public function failed(Throwable $e): void
    {
        $media = GeneratedMedia::query()->find($this->media->id);

        if ($media instanceof GeneratedMedia) {
            app(FailGeneratedMediaAction::class)->execute($media, GeneratedMediaFailure::TimedOut);
        }
    }
}
