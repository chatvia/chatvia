<?php

declare(strict_types=1);

namespace Domain\Conversation\Jobs;

use DateTimeInterface;
use Domain\Conversation\Actions\Media\CheckGeneratedVideoAction;
use Domain\Conversation\Actions\Media\FailGeneratedMediaAction;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\VideoCheckResult;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Throwable;

/**
 * Checks whether a started video is made, on the `generation` queue like
 * {@see GenerateMediaJob}. While the video is not ready, the job puts itself
 * back on the queue for the next check, so it stays one job (pending in
 * Horizon until the video settles) and no worker sleeps while it is made.
 */
#[Queue('generation')]
// Waiting is bounded by retryUntil(); a crash fails the video at once.
#[MaxExceptions(1)]
// Fetching a made video takes up to 180 seconds; below the pool's 300.
#[Timeout(240)]
#[FailOnTimeout]
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
        if ($action->execute($this->media) === VideoCheckResult::Waiting) {
            $this->release(CheckGeneratedVideoAction::INTERVAL_SECONDS);
        }
    }

    /**
     * A little past the video's own deadline, so the action's last check can
     * still find it made; past this, {@see failed()} times it out.
     */
    public function retryUntil(): DateTimeInterface
    {
        return $this->media->videoDeadline()->addMinute();
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
