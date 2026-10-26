<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Media;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Media\GeneratedMediaData;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Services\Media\GeneratedMediaFiles;
use Domain\Conversation\Services\Speech\ReadAloudAvailability;
use Domain\Llm\Contracts\ImageGenerator;
use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Makes the file of one pending item: a picture from the image service, or
 * the text read aloud by the speech service. The file is saved under the
 * agent's folder ({@see GeneratedMediaFiles}) and the item turns ready; when
 * the service refuses, fails or times out the item turns failed
 * ({@see FailGeneratedMediaAction}). Either way the chat is told at once.
 * An item that is no longer pending is left alone, so a job that runs twice
 * makes one file.
 */
class GenerateMediaAction
{
    public function __construct(
        private readonly ImageGenerator $images,
        private readonly Speaker $speaker,
        private readonly ReadAloudAvailability $readAloud,
        private readonly GeneratedMediaFiles $files,
        private readonly FailGeneratedMediaAction $fail,
    ) {}

    public function execute(GeneratedMedia $media): void
    {
        if ($media->status !== GeneratedMediaStatus::Pending) {
            return;
        }

        $agent = $media->agent;
        $conversation = $media->conversation;

        if (! $agent instanceof Agent || $conversation === null) {
            return;
        }

        try {
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

        $path = $this->files->pathFor($agent, $conversation->external_id, $media->external_id, $extension);
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
        $image = $this->images->generate(new ImagePromptData($media->prompt, ImageShape::tryFrom((string) $media->shape) ?? ImageShape::Square));

        return [$image->bytes, $image->mimeType, $image->extension()];
    }

    /**
     * @return array{string, string, string} the bytes, the MIME type and the extension
     */
    private function audio(GeneratedMedia $media, Agent $agent): array
    {
        $voice = filled($media->voice) ? (string) $media->voice : $this->readAloud->voiceFor($agent);
        $bytes = $this->speaker->speak($media->prompt, $voice, AiCallTag::AudioGeneration);
        $format = $this->speaker->format();

        return [$bytes, $format->mimeType(), $format->extension()];
    }

    private function failureOf(Throwable $e): GeneratedMediaFailure
    {
        return match (true) {
            $e instanceof ImageGenerationFailedException && $e->refused => GeneratedMediaFailure::Refused,
            $e instanceof ConnectionException, $e->getPrevious() instanceof ConnectionException => GeneratedMediaFailure::TimedOut,
            default => GeneratedMediaFailure::Failed,
        };
    }
}
