<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\ImageGenerator;
use Domain\Llm\Data\Image\GeneratedImageData;
use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Domain\Llm\Services\AiCallTags;
use Domain\Llm\Services\ProviderErrorReason;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Support\Http\ProviderRetry;
use Throwable;

/**
 * Pictures through the Gemini API's own `:generateContent` with image output,
 * with the model of `services.gemini.image_model` (gemini-nano-banana-2.1 by
 * default) and the Google key and address of Settings → AI providers. The
 * picture is an `inlineData` part of the first candidate. A prompt or a
 * picture Google's safety filters block is a refusal, not a failure.
 */
class GoogleImageGenerator implements ImageGenerator
{
    private const int TIMEOUT_SECONDS = 180;

    /** Why Google stops: these mean its rules blocked the prompt or the picture. */
    private const array REFUSALS = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION', 'IMAGE_SAFETY', 'IMAGE_PROHIBITED_CONTENT', 'IMAGE_RECITATION'];

    public function __construct(
        private readonly AiCallTags $tags,
        private readonly ProviderErrorReason $errors,
    ) {}

    public function generate(ImagePromptData $prompt): GeneratedImageData
    {
        $model = Str::chopStart($this->model(), 'models/');

        try {
            $response = $this->tags->during(AiCallTag::ImageGeneration, fn (): Response => ProviderRetry::request()
                ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.api_key')])
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(Str::rtrim((string) config('services.gemini.api_endpoint'), '/')."/models/{$model}:generateContent", [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt->prompt]]]],
                    'generationConfig' => [
                        'responseModalities' => ['TEXT', 'IMAGE'],
                        'imageConfig' => ['aspectRatio' => $prompt->shape->aspectRatio()],
                    ],
                ]));
        } catch (Throwable $e) {
            throw new ImageGenerationFailedException('Google could not be reached: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new ImageGenerationFailedException('Google could not make the image: '.($this->errors->of($response) ?? "HTTP {$response->status()}"));
        }

        foreach ((array) $response->json('candidates.0.content.parts') as $part) {
            $data = is_array($part) ? ($part['inlineData'] ?? $part['inline_data'] ?? null) : null;

            if (! is_array($data)) {
                continue;
            }

            $bytes = base64_decode((string) ($data['data'] ?? ''), true);

            if ($bytes !== false && filled($bytes)) {
                return new GeneratedImageData($bytes, (string) ($data['mimeType'] ?? $data['mime_type'] ?? 'image/png'));
            }
        }

        $blocked = $response->json('promptFeedback.blockReason') ?? $response->json('candidates.0.finishReason');

        if (in_array($blocked, self::REFUSALS, true)) {
            throw ImageGenerationFailedException::refused('Google');
        }

        throw new ImageGenerationFailedException('Google sent no image'.(filled($blocked) ? " ({$blocked})." : '.'));
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'google '.$this->model();
    }

    private function model(): string
    {
        return (string) config('services.gemini.image_model');
    }
}
