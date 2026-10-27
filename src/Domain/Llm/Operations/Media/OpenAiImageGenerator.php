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
 * Pictures through OpenAI's Responses API and its `image_generation` tool,
 * with the image model of `services.openai.image_model` (gpt-image-2.5-flare
 * by default) and the OpenAI key and address of Settings → AI providers. A
 * small text model takes the prompt and is made to call the tool once; the
 * response is not stored at OpenAI (`store: false`). A prompt OpenAI's safety
 * system blocks is a refusal, not a failure.
 */
class OpenAiImageGenerator implements ImageGenerator
{
    /** The text model that calls the image tool: a small one OpenAI lists for it. */
    private const string TEXT_MODEL = 'gpt-5.4-mini';

    private const int TIMEOUT_SECONDS = 180;

    /** Error codes OpenAI answers a blocked prompt with. */
    private const array REFUSAL_CODES = ['moderation_blocked', 'content_policy_violation', 'content_filter'];

    private const string INSTRUCTIONS = 'Draw the picture the user describes with the image generation tool, once. Keep to the description: do not add text, people or objects it does not ask for.';

    public function __construct(
        private readonly AiCallTags $tags,
        private readonly ProviderErrorReason $errors,
    ) {}

    public function generate(ImagePromptData $prompt): GeneratedImageData
    {
        try {
            $response = $this->tags->during(AiCallTag::ImageGeneration, fn (): Response => ProviderRetry::request()
                ->withToken((string) config('services.openai.api_key'))
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(Str::rtrim((string) config('services.openai.api_endpoint'), '/').'/responses', [
                    'model' => self::TEXT_MODEL,
                    'store' => false,
                    'instructions' => self::INSTRUCTIONS,
                    'input' => $prompt->prompt,
                    'tools' => [[
                        'type' => 'image_generation',
                        'model' => $this->model($prompt->model),
                        'action' => 'generate',
                        'size' => $prompt->shape->size(),
                        'output_format' => 'png',
                    ]],
                    'tool_choice' => ['type' => 'image_generation'],
                ]));
        } catch (Throwable $e) {
            throw new ImageGenerationFailedException("OpenAI could not be reached: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            $reason = $this->errors->of($response) ?? "HTTP {$response->status()}";

            throw collect(self::REFUSAL_CODES)->containsStrict($response->json('error.code'))
                ? ImageGenerationFailedException::refused('OpenAI', $reason)
                : new ImageGenerationFailedException("OpenAI could not make the image: {$reason}");
        }

        $output = collect((array) $response->json('output'));
        $image = $output->first(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'image_generation_call' && filled($item['result'] ?? null));
        $bytes = is_array($image) ? base64_decode((string) $image['result'], true) : false;

        if ($bytes !== false && filled($bytes)) {
            return new GeneratedImageData($bytes, 'image/png');
        }

        $refusal = $output
            ->flatMap(fn (mixed $item): array => is_array($item) && ($item['type'] ?? null) === 'message' ? (array) ($item['content'] ?? []) : [])
            ->first(fn (mixed $part): bool => is_array($part) && ($part['type'] ?? null) === 'refusal');

        if (is_array($refusal) || $response->json('incomplete_details.reason') === 'content_filter') {
            throw ImageGenerationFailedException::refused('OpenAI', is_array($refusal) ? (string) ($refusal['refusal'] ?? '') : '');
        }

        throw new ImageGenerationFailedException('OpenAI sent no image.');
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(?string $model = null): string
    {
        return "openai {$this->model($model)}";
    }

    private function model(?string $picked = null): string
    {
        return filled($picked) ? $picked : (string) config('services.openai.image_model');
    }
}
