<?php

declare(strict_types=1);

namespace Domain\Conversation\Jobs;

use Domain\Conversation\Actions\Media\FailGeneratedMediaAction;
use Domain\Conversation\Actions\Media\GenerateMediaAction;
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
 * Makes one generated picture or audio file (or starts a video) off the chat request, on the
 * `generation` queue (its own Horizon pool), so a slow image service never
 * holds up replies.
 */
#[Queue('generation')]
// One try: a failed file is shown as failed at once and its slot given back.
#[Tries(1)]
// Above the services' own 180-second timeouts, below the pool's 300.
#[Timeout(240)]
// The conversation, and with it the item, may be deleted before the job runs.
#[DeleteWhenMissingModels]
class GenerateMediaJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly GeneratedMedia $media,
    ) {}

    public function handle(GenerateMediaAction $action): void
    {
        $action->execute($this->media);
    }

    /** The job ran out of time or crashed: the chat must not wait for good. */
    public function failed(Throwable $e): void
    {
        $media = GeneratedMedia::query()->find($this->media->id);

        if ($media instanceof GeneratedMedia) {
            app(FailGeneratedMediaAction::class)->execute($media, GeneratedMediaFailure::TimedOut);
        }
    }
}
