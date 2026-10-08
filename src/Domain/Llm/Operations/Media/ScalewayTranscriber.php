<?php

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Providers\Scaleway;
use Illuminate\Support\Facades\Http;

/**
 * Speech-to-text through the OpenAI-compatible `/audio/transcriptions`
 * endpoint of the Scaleway Generative APIs (an open-weight Whisper model by
 * default, set in `services.scalewayai.transcription_model`).
 */
class ScalewayTranscriber implements Transcriber
{
    public function transcribe(string $filePath): string
    {
        $response = Http::withToken((string) config('services.scalewayai.api_key'))
            ->timeout(86000)
            ->attach(
                'file',
                file_get_contents($filePath),
                'audio.mp3'
            )
            ->post(Scaleway::baseUrl().'/audio/transcriptions', [
                'model' => (string) config('services.scalewayai.transcription_model'),
            ])->throw()->json();

        return str($response['text'])->trim()->toString();
    }
}
