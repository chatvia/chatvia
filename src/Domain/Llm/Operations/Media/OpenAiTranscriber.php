<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Services\AiCallTags;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Speech-to-text through OpenAI's `/audio/transcriptions` endpoint (Whisper,
 * `whisper-1` by default, set in `services.openai.transcription_model`), with
 * the OpenAI key and address of Settings → AI providers. OpenAI takes files of up to
 * 25 MB in the formats below; anything else is first compressed to MP3
 * (32 kbps fits about an hour and a half).
 */
class OpenAiTranscriber implements Transcriber
{
    /** The formats OpenAI reads, by extension. */
    private const array FORMATS = ['flac', 'mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'ogg', 'wav', 'webm'];

    /** OpenAI refuses larger uploads. */
    private const int MAX_BYTES = 25 * 1024 * 1024;

    public function __construct(
        private readonly AiCallTags $tags,
    ) {}

    public function transcribe(string $filePath): string
    {
        // OpenAI tells the format by the file name.
        $response = $this->tags->during(AiCallTag::Transcription, fn (): mixed => Http::withToken((string) config('services.openai.api_key'))
            ->timeout(3600)
            ->attach('file', File::get($filePath), 'audio.'.strtolower(pathinfo($filePath, PATHINFO_EXTENSION)))
            ->post(rtrim((string) config('services.openai.api_endpoint'), '/').'/audio/transcriptions', [
                'model' => $this->name(),
            ])->throw()->json());

        return str((string) ($response['text'] ?? ''))->trim()->toString();
    }

    public function takesAsIs(string $extension, int $bytes): bool
    {
        return in_array($extension, self::FORMATS, true) && $bytes > 0 && $bytes <= self::MAX_BYTES;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(): string
    {
        return (string) config('services.openai.transcription_model');
    }
}
