<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Media;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Media\GeneratedMediaData;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Jobs\CheckGeneratedVideoJob;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Services\Media\GeneratedMediaFiles;
use Domain\Conversation\Services\Speech\ReadAloudAvailability;
use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Enums\VideoShape;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Services\AudioGeneration;
use Domain\Llm\Services\MediaGenerators;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Makes the file of one pending item: a picture from the image service kept
 * on the item ({@see MediaGenerators}), or
 * the text read aloud by the speech service of audio files ({@see AudioGeneration}). The file is saved under the
 * agent's folder ({@see GeneratedMediaFiles}) and the item turns ready; when
 * the service refuses, fails or times out the item turns failed
 * ({@see FailGeneratedMediaAction}). Either way the chat is told at once.
 * A video takes minutes, so it is only started here: its task id is kept
 * and {@see CheckGeneratedVideoAction} fetches it once it is made.
 * An item that is no longer pending (or a video already started) is left
 * alone, so a job that runs twice makes one file.
 */
class GenerateMediaAction
{
    public function __construct(
        private readonly MediaGenerators $generators,
        private readonly AudioGeneration $audio,
        private readonly ReadAloudAvailability $readAloud,
        private readonly GeneratedMediaFiles $files,
        private readonly FailGeneratedMediaAction $fail,
    ) {}

    public function execute(GeneratedMedia $media): void
    {
        if ($media->status !== GeneratedMediaStatus::Pending || filled($media->task_id)) {
            return;
        }

        $agent = $media->agent;
        $conversation = $media->conversation;

        if (! $agent instanceof Agent || $conversation === null) {
            return;
        }

        try {
            if ($media->kind === GeneratedMediaKind::Video) {
                $this->startVideo($media);

                return;
            }

            [$bytes, $mimeType, $extension] = $media->kind === GeneratedMediaKind::Image
                ? $this->image($media)
                : $this->audio($media, $agent);
        } catch (Throwable $e) {
            Log::warning('A generated file could not be made', [
                'generated_media_id' => $media->id,
                'service' => $media->service,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 500),
            ]);

            $this->fail->execute($media, $this->failureOf($e));

            return;
        }

        $path = $this->files->pathFor($agent, $conversation->external_id, $media, $extension);
        Storage::disk(GeneratedMedia::DISK)->put($path, $bytes);

        $media->update([
            'status' => GeneratedMediaStatus::Ready,
            'filepath' => $path,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($bytes),
        ]);

        GeneratedMediaUpdated::dispatch($conversation->external_id, GeneratedMediaData::fromModel($media));
    }

    /**
     * @return array{string, string, string} the bytes, the MIME type and the extension
     */
    private function image(GeneratedMedia $media): array
    {
        $image = $this->generators->image(AiProvider::tryFrom($media->service))->generate(new ImagePromptData(
            prompt: $media->prompt,
            shape: ImageShape::tryFrom((string) ($media->metadata['shape'] ?? '')) ?? ImageShape::Square,
            model: $media->metadata['model'] ?? null,
        ));

        return [$image->bytes, $image->mimeType, $image->extension()];
    }

    /** Asks for the video with the length and resolution saved when it was asked for, then checks on it from time to time. */
    private function startVideo(GeneratedMedia $media): void
    {
        $metadata = $media->metadata ?? [];

        $taskId = $this->generators->video(AiProvider::tryFrom($media->service))->start(new VideoPromptData(
            prompt: $media->prompt,
            shape: VideoShape::tryFrom((string) ($metadata['shape'] ?? '')) ?? VideoShape::Landscape,
            seconds: (int) ($metadata['seconds'] ?? config('chatvia.ai.video_duration')),
            resolution: VideoResolution::tryFrom((string) ($metadata['resolution'] ?? config('chatvia.ai.video_resolution'))) ?? VideoResolution::Hd720,
            model: $metadata['model'] ?? null,
        ));

        $media->update(['task_id' => $taskId]);

        CheckGeneratedVideoJob::dispatch($media)->delay(now()->addSeconds(CheckGeneratedVideoAction::INTERVAL_SECONDS));
    }

    /**
     * @return array{string, string, string} the bytes, the MIME type and the extension
     */
    private function audio(GeneratedMedia $media, Agent $agent): array
    {
        $speaker = $this->audio->speaker;
        $voice = filled($media->metadata['voice'] ?? null) ? (string) $media->metadata['voice'] : $this->readAloud->voiceFor($agent, $speaker);
        $bytes = $speaker->speak($media->prompt, $voice, AiCallTag::AudioGeneration);
        $format = $speaker->format();

        return [$bytes, $format->mimeType(), $format->extension()];
    }

    private function failureOf(Throwable $e): GeneratedMediaFailure
    {
        return match (true) {
            $e instanceof ImageGenerationFailedException && $e->refused,
            $e instanceof VideoGenerationFailedException && $e->refused => GeneratedMediaFailure::Refused,
            $e instanceof ConnectionException, $e->getPrevious() instanceof ConnectionException => GeneratedMediaFailure::TimedOut,
            default => GeneratedMediaFailure::Failed,
        };
    }
}
