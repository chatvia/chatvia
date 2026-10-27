<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\ImageGenerator;
use Domain\Llm\Data\Image\GeneratedImageData;
use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Domain\Llm\Services\AiCallTags;
use Domain\Llm\Services\OpenRouterImageModels;
use Domain\Llm\Services\ProviderErrorReason;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Support\Http\ProviderRetry;
use Throwable;

/**
 * Pictures through OpenRouter's chat completions with image output
 * (`modalities`), with the model of `services.openrouter.image_model`
 * (google/gemini-nano-banana-2.1 by default) and the OpenRouter key of
 * Settings → AI providers. The picture comes back as a data URL in
 * `message.images`. A model that writes only images is asked for images
 * only ({@see OpenRouterImageModels::writesText()}). A prompt flagged by
 * moderation (403) or stopped by the model's safety filter is a refusal.
 */
class OpenRouterImageGenerator implements ImageGenerator
{
    private const string ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    private const int TIMEOUT_SECONDS = 180;

    public function __construct(
        private readonly AiCallTags $tags,
        private readonly ProviderErrorReason $errors,
        private readonly OpenRouterImageModels $models,
    ) {}

    public function generate(ImagePromptData $prompt): GeneratedImageData
    {
        $model = $this->model($prompt->model);

        try {
            $response = $this->tags->during(AiCallTag::ImageGeneration, fn (): Response => ProviderRetry::request()
                ->withToken((string) config('services.openrouter.api_key'))
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::ENDPOINT, [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => $prompt->prompt]],
                    'modalities' => $this->models->writesText($model) ? ['image', 'text'] : ['image'],
                    'image_config' => ['aspect_ratio' => $prompt->shape->aspectRatio()],
                ]));
        } catch (Throwable $e) {
            throw new ImageGenerationFailedException('OpenRouter could not be reached: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $reason = $this->errors->of($response) ?? "HTTP {$response->status()}";

            throw $response->status() === 403
                ? ImageGenerationFailedException::refused('OpenRouter', $reason)
                : new ImageGenerationFailedException("OpenRouter could not make the image: {$reason}");
        }

        foreach ((array) $response->json('choices.0.message.images') as $image) {
            $picture = is_array($image) ? $this->fromDataUrl((string) data_get($image, 'image_url.url')) : null;

            if ($picture instanceof GeneratedImageData) {
                return $picture;
            }
        }

        $finish = Str::upper((string) ($response->json('choices.0.native_finish_reason') ?? ''));

        if ($response->json('choices.0.finish_reason') === 'content_filter' || Str::contains($finish, ['SAFETY', 'PROHIBITED', 'BLOCKLIST'])) {
            throw ImageGenerationFailedException::refused('OpenRouter');
        }

        throw new ImageGenerationFailedException('OpenRouter sent no image.');
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(?string $model = null): string
    {
        return 'openrouter '.$this->model($model);
    }

    /** A picture from a `data:image/…;base64,…` URL; null for anything else. */
    private function fromDataUrl(string $url): ?GeneratedImageData
    {
        if (! preg_match('#^data:(image/[\w.+-]+);base64,(.+)$#s', $url, $parts)) {
            return null;
        }

        $bytes = base64_decode($parts[2], true) ?: '';

        return filled($bytes) ? new GeneratedImageData($bytes, $parts[1]) : null;
    }

    private function model(?string $picked = null): string
    {
        return filled($picked) ? $picked : (string) config('services.openrouter.image_model');
    }
}
