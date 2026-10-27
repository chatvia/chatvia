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
use Domain\Llm\Services\VideoDownloader;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Support\Http\ProviderRetry;
use Throwable;

/**
 * Seedance videos through BytePlus ModelArk (`/contents/generations/tasks`),
 * with the BytePlus key and address of Settings → AI providers and the model
 * of `services.byteplus.video_model` (Seedance 2.0 Fast by default). Seedance
 * 2.0 works only once a prepaid resource pack is bought in the BytePlus
 * console. A prompt or a video its checks flag (error codes that name
 * sensitive content) is a refusal. The task is deleted once its video is
 * saved.
 */
class SeedanceVideoGenerator implements VideoGenerator
{
    /** The offered models, and whether each makes 1080p. Both make 4 to 15 seconds. */
    public const array MODELS = [
        'dreamina-seedance-2-0-fast-260128' => false,
        'dreamina-seedance-2-0-260128' => true,
    ];

    public function __construct(
        private readonly AiCallTags $tags,
        private readonly VideoDownloader $downloads,
    ) {}

    public function start(VideoPromptData $prompt): string
    {
        $model = $this->model($prompt->model);
        $hd = (self::MODELS[$model] ?? false) && $prompt->resolution === VideoResolution::Hd1080;

        $response = $this->call(fn (PendingRequest $ark): Response => $ark->post("{$this->endpoint()}/contents/generations/tasks", [
            'model' => $model,
            'content' => [['type' => 'text', 'text' => $prompt->prompt]],
            'ratio' => $prompt->shape->aspectRatio(),
            'duration' => $prompt->secondsBetween(4, 15),
            'resolution' => $hd ? '1080p' : '720p',
            'generate_audio' => true,
            'watermark' => false,
        ]));

        return (string) ($response->json('id') ?? throw new VideoGenerationFailedException('BytePlus did not start the video.'));
    }

    public function check(string $taskId): ?GeneratedVideoData
    {
        $response = $this->call(fn (PendingRequest $ark): Response => $ark->get("{$this->endpoint()}/contents/generations/tasks/".rawurlencode($taskId)));

        return match ((string) $response->json('status')) {
            'succeeded' => filled($response->json('content.video_url'))
                ? $this->downloads->fromUrl((string) $response->json('content.video_url'), 'BytePlus')
                : throw new VideoGenerationFailedException('BytePlus sent no video.'),
            'failed', 'expired', 'cancelled' => throw $this->failure((string) $response->json('error.code'), (string) ($response->json('error.message') ?? $response->json('status'))),
            default => null,
        };
    }

    public function forget(string $taskId): void
    {
        rescue(fn () => Http::withToken((string) config('services.byteplus.api_key'))->timeout(15)->delete("{$this->endpoint()}/contents/generations/tasks/".rawurlencode($taskId)), report: false);
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(?string $model = null): string
    {
        return "byteplus {$this->model($model)}";
    }

    /** @param callable(PendingRequest): Response $request */
    private function call(callable $request): Response
    {
        try {
            $response = $this->tags->during(AiCallTag::VideoGeneration, fn (): Response => $request(
                ProviderRetry::on(Http::withToken((string) config('services.byteplus.api_key'))->acceptJson()->timeout(60)),
            ));
        } catch (Throwable $e) {
            throw new VideoGenerationFailedException("BytePlus could not be reached: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw $this->failure((string) $response->json('error.code'), (string) ($response->json('error.message') ?? "HTTP {$response->status()}"));
        }

        return $response;
    }

    /** ModelArk names flagged content in the error code, such as OutputVideoSensitiveContentDetected. */
    private function failure(string $code, string $reason): VideoGenerationFailedException
    {
        return Str::contains($code, 'Sensitive')
            ? VideoGenerationFailedException::refused('BytePlus', $reason)
            : new VideoGenerationFailedException('BytePlus could not make the video: '.Str::trim("{$code} {$reason}"));
    }

    private function endpoint(): string
    {
        return Str::rtrim((string) config('services.byteplus.endpoint'), '/');
    }

    private function model(?string $picked = null): string
    {
        return filled($picked) ? $picked : (string) config('services.byteplus.video_model');
    }
}
