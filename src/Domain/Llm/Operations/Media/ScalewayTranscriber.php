<?php

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Providers\Scaleway;
use Domain\Llm\Services\AiCallTags;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Speech-to-text through the OpenAI-compatible `/audio/transcriptions`
 * endpoint of the Scaleway Generative APIs (an open-weight Whisper model by
 * default, set in `services.scalewayai.transcription_model`). Scaleway lists
 * only MP3 and WAV and gives no size limit, so the same 25 MB as OpenAI is
 * kept; anything else is first compressed to MP3.
 */
class ScalewayTranscriber implements Transcriber
{
    /** The formats Scaleway reads, by extension. */
    private const array FORMATS = ['mp3', 'wav'];

    private const int MAX_BYTES = 25 * 1024 * 1024;

    public function __construct(
        private readonly AiCallTags $tags,
    ) {}

    public function transcribe(string $filePath): string
    {
        // The endpoint tells the format by the file name.
        $response = $this->tags->during(AiCallTag::Transcription, fn (): mixed => Http::withToken((string) config('services.scalewayai.api_key'))
            ->timeout(86000)
            ->attach(
                'file',
                File::get($filePath),
                'audio.'.strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
            )
            ->post(Scaleway::baseUrl().'/audio/transcriptions', [
                'model' => (string) config('services.scalewayai.transcription_model'),
            ])->throw()->json());

        return str($response['text'])->trim()->toString();
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
        return (string) config('services.scalewayai.transcription_model');
    }
}
