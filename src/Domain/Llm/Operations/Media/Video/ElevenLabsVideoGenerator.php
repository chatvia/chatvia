<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media\Video;

use Domain\Llm\Contracts\VideoGenerator;
use Domain\Llm\Data\Video\GeneratedVideoData;
use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Services\AiCallTags;
use Domain\Llm\Services\ProviderErrorReason;
use Domain\Llm\Services\VideoDownloader;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Support\Http\ProviderRetry;
use Throwable;

/**
 * Videos through ElevenLabs (`/flows/video`), with the ElevenLabs key and
 * address of Settings → AI providers and the model of
 * `services.elevenlabs.video_model` (Google's Veo 3.1 Fast by default; it
 * also serves ByteDance's Seedance, which ElevenLabs must approve first).
 * The API needs a Pro plan or above. A made video is a signed link that
 * lasts an hour, fetched without the key. A video ElevenLabs moderated is a
 * refusal; a failed one costs nothing.
 */
class ElevenLabsVideoGenerator implements VideoGenerator
{
    /**
     * The offered models: the lengths each makes (a few to choose from, or a
     * range), and whether it makes 1080p. Veo, the one with a few lengths to
     * choose from, makes 1080p only at its longest.
     */
    public const array MODELS = [
        'veo-3.1-fast-generate-001' => ['choices' => [4, 6, 8], 'hd' => true],
        'veo-3.1-generate-001' => ['choices' => [4, 6, 8], 'hd' => true],
        'bytedance-seedance-v2' => ['between' => [4, 15], 'hd' => true],
        'bytedance-seedance-v2-fast' => ['between' => [4, 15], 'hd' => false],
        'bytedance-seedance-v2.5' => ['between' => [4, 15], 'hd' => true],
    ];

    public function __construct(
        private readonly AiCallTags $tags,
        private readonly ProviderErrorReason $errors,
        private readonly VideoDownloader $downloads,
    ) {}

    public function start(VideoPromptData $prompt): string
    {
        $model = $this->model();
        $facts = self::MODELS[$model] ?? self::MODELS['veo-3.1-fast-generate-001'];
        $hd = $facts['hd'] && $prompt->resolution === VideoResolution::Hd1080;
        $seconds = match (true) {
            isset($facts['between']) => $prompt->secondsBetween(...$facts['between']),
            $hd => max($facts['choices']),
            default => $prompt->secondsAmong($facts['choices']),
        };

        $response = $this->call(fn (PendingRequest $elevenLabs): Response => $elevenLabs->post($this->endpoint().'/flows/video', [
            'model_id' => $model,
            'prompt' => $prompt->prompt,
            'aspect_ratio' => $prompt->shape->aspectRatio(),
            'duration_secs' => $seconds,
            'resolution' => $hd ? '1080p' : '720p',
            'generate_audio' => true,
        ]));

        return (string) ($response->json('id') ?? throw new VideoGenerationFailedException('ElevenLabs did not start the video.'));
    }

    public function check(string $taskId): ?GeneratedVideoData
    {
        $response = $this->call(fn (PendingRequest $elevenLabs): Response => $elevenLabs->get($this->endpoint().'/flows/video/'.rawurlencode($taskId)));

        return match ((string) $response->json('status')) {
            'completed' => $this->downloads->fromUrl((string) $response->json('content_url'), 'ElevenLabs'),
            'failed' => throw $response->json('failure_reason') === 'moderated'
                ? VideoGenerationFailedException::refused('ElevenLabs', (string) $response->json('error_message'))
                : new VideoGenerationFailedException('ElevenLabs could not make the video: '.($response->json('error_message') ?? $response->json('failure_reason'))),
            default => null,
        };
    }

    public function forget(string $taskId): void {}

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'elevenlabs '.$this->model();
    }

    /** @param callable(PendingRequest): Response $request */
    private function call(callable $request): Response
    {
        try {
            $response = $this->tags->during(AiCallTag::VideoGeneration, fn (): Response => $request(
                ProviderRetry::on(Http::withHeaders(['xi-api-key' => (string) config('services.elevenlabs.api_key')])->acceptJson()->timeout(60)),
            ));
        } catch (Throwable $e) {
            throw new VideoGenerationFailedException('ElevenLabs could not be reached: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $reason = $this->errors->of($response)
                ?? (string) ($response->json('detail.message') ?? $response->json('detail.0.msg') ?? "HTTP {$response->status()}");

            throw new VideoGenerationFailedException("ElevenLabs could not make the video: {$reason}");
        }

        return $response;
    }

    private function endpoint(): string
    {
        return Str::rtrim((string) config('services.elevenlabs.endpoint'), '/');
    }

    private function model(): string
    {
        return (string) config('services.elevenlabs.video_model');
    }
}
