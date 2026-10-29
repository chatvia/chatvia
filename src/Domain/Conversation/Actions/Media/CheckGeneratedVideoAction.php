<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Media;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Media\GeneratedMediaData;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Jobs\CheckGeneratedVideoJob;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Services\Media\GeneratedMediaFiles;
use Domain\Llm\Data\Video\GeneratedVideoData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Services\MediaGenerators;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\File as LocalFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Asks the video service that started a video (the one kept on the item,
 * {@see MediaGenerators}) whether it is made. While it is not,
 * it asks again in a little while ({@see CheckGeneratedVideoJob}), so no
 * worker waits on a video; a service that cannot be reached for a moment is
 * asked again too. Once made, the video is saved under the agent's folder
 * ({@see GeneratedMediaFiles}), the item turns ready, the service's copy is
 * deleted where it can be, and the chat is told at once. A refused or
 * failed video, or one not made within `chatvia.ai.video_max_wait_minutes`,
 * turns failed ({@see FailGeneratedMediaAction}), which gives its slot of
 * the monthly video limit back.
 */
class CheckGeneratedVideoAction
{
    /** How long to wait between two checks, in seconds. */
    public const int INTERVAL_SECONDS = 15;

    public function __construct(
        private readonly MediaGenerators $generators,
        private readonly GeneratedMediaFiles $files,
        private readonly FailGeneratedMediaAction $fail,
    ) {}

    public function execute(GeneratedMedia $media): void
    {
        $taskId = $media->task_id ?? '';
        $agent = $media->agent;
        $conversation = $media->conversation;

        if ($media->status !== GeneratedMediaStatus::Pending || $taskId === '' || ! $agent instanceof Agent || $conversation === null) {
            return;
        }

        try {
            $videos = $this->generators->video(AiProvider::tryFrom($media->service));
            $video = $videos->check($taskId);
        } catch (Throwable $e) {
            if ($this->isMomentary($e)) {
                $this->checkLater($media);

                return;
            }

            Log::warning('A generated video could not be made', [
                'generated_media_id' => $media->id,
                'service' => $media->service,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 500),
            ]);

            $this->fail->execute($media, $e instanceof VideoGenerationFailedException && $e->refused ? GeneratedMediaFailure::Refused : GeneratedMediaFailure::Failed);

            return;
        }

        if (! $video instanceof GeneratedVideoData) {
            $this->checkLater($media);

            return;
        }

        $path = $this->files->pathFor($agent, $conversation->external_id, $media, $video->extension());

        try {
            $size = File::size($video->path);
            Storage::disk(GeneratedMedia::DISK)->putFileAs(Str::beforeLast($path, '/'), new LocalFile($video->path), Str::afterLast($path, '/'));
        } finally {
            File::delete($video->path);
        }

        $saved = GeneratedMedia::query()
            ->whereKey($media->id)
            ->where('status', GeneratedMediaStatus::Pending)
            ->update([
                'status' => GeneratedMediaStatus::Ready,
                'filepath' => $path,
                'mime_type' => $video->mimeType,
                'size_bytes' => $size,
                'updated_at' => now(),
            ]);

        // It timed out meanwhile: the chat already says so, so the file goes.
        if ($saved === 0) {
            Storage::disk(GeneratedMedia::DISK)->delete($path);

            return;
        }

        $videos->forget($taskId);
        $media->refresh();

        GeneratedMediaUpdated::dispatch($conversation->external_id, GeneratedMediaData::fromModel($media));
    }

    /** Checks again in a little while, unless the video has had all its time. */
    private function checkLater(GeneratedMedia $media): void
    {
        $deadline = ($media->created_at ?? now())->copy()->addMinutes(max(1, (int) config('chatvia.ai.video_max_wait_minutes')));

        if ($deadline->isPast()) {
            $this->fail->execute($media, GeneratedMediaFailure::TimedOut);

            return;
        }

        CheckGeneratedVideoJob::dispatch($media)->delay(now()->addSeconds(self::INTERVAL_SECONDS));
    }

    /** The service could not be reached this time; the video may still be on its way. */
    private function isMomentary(Throwable $e): bool
    {
        return $e instanceof ConnectionException || $e->getPrevious() instanceof ConnectionException;
    }
}
