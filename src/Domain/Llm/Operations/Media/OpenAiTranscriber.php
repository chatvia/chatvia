<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\Transcriber;
use Illuminate\Support\Facades\Http;

/**
 * Speech-to-text through OpenAI's `/audio/transcriptions` endpoint (Whisper,
 * `whisper-1` by default, set in `services.openai.transcription_model`), with
 * the OpenAI key and address of Settings → AI providers. OpenAI takes files of up to
 * 25 MB: the compressed audio (32 kbps) fits about an hour and a half.
 */
class OpenAiTranscriber implements Transcriber
{
    public function transcribe(string $filePath): string
    {
        $response = Http::withToken((string) config('services.openai.api_key'))
            ->timeout(3600)
            ->attach('file', (string) file_get_contents($filePath), 'audio.mp3')
            ->post(rtrim((string) config('services.openai.api_endpoint'), '/').'/audio/transcriptions', [
                'model' => $this->name(),
            ])->throw()->json();

        return str((string) ($response['text'] ?? ''))->trim()->toString();
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
