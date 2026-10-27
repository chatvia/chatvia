<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\ImageGenerator;
use Domain\Llm\Data\Image\GeneratedImageData;
use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Domain\Llm\Services\AiCallTags;
use Domain\Llm\Services\FalQueue;
use Domain\Llm\Services\ProviderErrorReason;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pictures through fal.ai (`fal.run`, which waits for the picture), with the
 * model of `services.fal.image_model` (FLUX.2 dev, an open model, by
 * default) and the fal.ai key of Settings → AI providers. fal's models name
 * the shape two ways ({@see MODELS}); the picture comes back as a link that
 * is fetched at once. A prompt fal's content rules block, or a picture its
 * safety checker flags (sent back black), is a refusal, not a failure.
 */
class FalImageGenerator implements ImageGenerator
{
    private const int TIMEOUT_SECONDS = 180;

    /** The offered models, and the field each takes the shape in. */
    public const array MODELS = [
        'fal-ai/flux-2' => 'image_size',
        'fal-ai/qwen-image' => 'image_size',
        'fal-ai/flux-2-pro' => 'image_size',
        'fal-ai/nano-banana-2' => 'aspect_ratio',
        'fal-ai/nano-banana-pro' => 'aspect_ratio',
        'fal-ai/bytedance/seedream/v4.5/text-to-image' => 'image_size',
        'fal-ai/recraft/v3/text-to-image' => 'image_size',
    ];

    public function __construct(
        private readonly AiCallTags $tags,
        private readonly ProviderErrorReason $errors,
        private readonly FalQueue $fal,
    ) {}

    public function generate(ImagePromptData $prompt): GeneratedImageData
    {
        $model = $this->model();

        try {
            $response = $this->tags->during(AiCallTag::ImageGeneration, fn (): Response => $this->fal->run($model, [
                'prompt' => $prompt->prompt,
                'num_images' => 1,
                ...$this->shape($model, $prompt->shape),
            ], self::TIMEOUT_SECONDS));
        } catch (Throwable $e) {
            throw new ImageGenerationFailedException('fal.ai could not be reached: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $reason = $this->errors->of($response) ?? (string) ($response->json('detail.0.msg') ?? "HTTP {$response->status()}");

            throw $this->isPolicyError($response)
                ? ImageGenerationFailedException::refused('fal.ai', $reason)
                : new ImageGenerationFailedException("fal.ai could not make the image: {$reason}");
        }

        if (in_array(true, (array) $response->json('has_nsfw_concepts'), true)) {
            throw ImageGenerationFailedException::refused('fal.ai');
        }

        $url = (string) $response->json('images.0.url');

        if (blank($url)) {
            throw new ImageGenerationFailedException('fal.ai sent no image.');
        }

        try {
            $file = Http::timeout(60)->get($url)->throw();
        } catch (Throwable $e) {
            throw new ImageGenerationFailedException('The image fal.ai made could not be fetched: '.$e->getMessage(), previous: $e);
        }

        $mimeType = Str::before((string) ($response->json('images.0.content_type') ?? $file->header('Content-Type')), ';');

        return new GeneratedImageData($file->body(), filled($mimeType) ? $mimeType : 'image/png');
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'fal '.$this->model();
    }

    /**
     * The shape in the field the model takes: a named size, or a ratio.
     *
     * @return array<string, string>
     */
    private function shape(string $model, ImageShape $shape): array
    {
        if ((self::MODELS[$model] ?? 'image_size') === 'aspect_ratio') {
            return ['aspect_ratio' => $shape->aspectRatio()];
        }

        return ['image_size' => match ($shape) {
            ImageShape::Square => 'square_hd',
            ImageShape::Landscape => 'landscape_4_3',
            ImageShape::Portrait => 'portrait_4_3',
        }];
    }

    /** fal names a blocked prompt in the error's type: `content_policy_violation` and the like. */
    private function isPolicyError(Response $response): bool
    {
        $types = collect((array) $response->json('detail'))
            ->map(fn (mixed $item): string => is_array($item) ? (string) ($item['type'] ?? '') : '')
            ->push((string) $response->json('error_type'));

        return $types->contains(fn (string $type): bool => Str::contains($type, ['content_policy', 'nsfw', 'safety']));
    }

    private function model(): string
    {
        return (string) config('services.fal.image_model');
    }
}
