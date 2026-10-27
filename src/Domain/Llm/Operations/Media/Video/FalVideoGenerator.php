<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media\Video;

use Closure;
use Domain\Llm\Contracts\VideoGenerator;
use Domain\Llm\Data\Video\GeneratedVideoData;
use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Services\AiCallTags;
use Domain\Llm\Services\FalQueue;
use Domain\Llm\Services\ProviderErrorReason;
use Domain\Llm\Services\VideoDownloader;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Throwable;

/**
 * Videos through fal.ai's queue ({@see FalQueue}), with the fal.ai key of
 * Settings → AI providers and the model of `services.fal.video_model` (Wan
 * 2.2, an open model, by default; fal also serves Veo 3.1 and Kling). fal's
 * models name length and size their own ways, so each offered model says
 * which it takes ({@see MODELS}). The task id is the request's queue path.
 * A prompt fal's content rules block is a refusal.
 */
class FalVideoGenerator implements VideoGenerator
{
    /** Wan's frame rate: its length is a number of frames. */
    private const int WAN_FPS = 16;

    /** The offered models, and how each takes length and size. */
    public const array MODELS = [
        'fal-ai/wan/v2.2-a14b/text-to-video' => 'wan',
        'fal-ai/veo3.1/fast' => 'veo',
        'fal-ai/veo3.1' => 'veo',
        'fal-ai/veo3.1/lite' => 'veo',
        'fal-ai/kling-video/v3/pro/text-to-video' => 'kling',
    ];

    public function __construct(
        private readonly AiCallTags $tags,
        private readonly ProviderErrorReason $errors,
        private readonly FalQueue $fal,
        private readonly VideoDownloader $downloads,
    ) {}

    public function start(VideoPromptData $prompt): string
    {
        $model = $this->model($prompt->model);
        $response = $this->call(fn (): Response => $this->fal->submit($model, ['prompt' => $prompt->prompt, ...$this->input($model, $prompt)]));

        if (blank($response->json('request_id'))) {
            throw new VideoGenerationFailedException('fal.ai did not start the video.');
        }

        return $this->fal->requestPath($model, $response);
    }

    public function check(string $taskId): ?GeneratedVideoData
    {
        $status = $this->call(fn (): Response => $this->fal->status($taskId));

        if ($status->json('status') !== 'COMPLETED') {
            return null;
        }

        if (filled($status->json('error'))) {
            throw $this->failure((string) $status->json('error_type'), (string) $status->json('error'));
        }

        $url = (string) $this->call(fn (): Response => $this->fal->result($taskId))->json('video.url');

        return filled($url)
            ? $this->downloads->fromUrl($url, 'fal.ai')
            : throw new VideoGenerationFailedException('fal.ai sent no video.');
    }

    public function forget(string $taskId): void {}

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(?string $model = null): string
    {
        return 'fal '.$this->model($model);
    }

    /**
     * Length, size and sound in the fields the model takes.
     *
     * @return array<string, mixed>
     */
    private function input(string $model, VideoPromptData $prompt): array
    {
        $hd = $prompt->resolution === VideoResolution::Hd1080;

        return match (self::MODELS[$model] ?? 'veo') {
            'wan' => [
                'aspect_ratio' => $prompt->shape->aspectRatio(),
                'resolution' => '720p',
                'num_frames' => $prompt->secondsBetween(1, 10) * self::WAN_FPS + 1,
                'frames_per_second' => self::WAN_FPS,
                'enable_safety_checker' => true,
            ],
            'kling' => [
                'aspect_ratio' => $prompt->shape->aspectRatio(),
                'duration' => (string) $prompt->secondsBetween(3, 15),
                'generate_audio' => true,
            ],
            // Veo makes 1080p only at its longest length.
            default => [
                'aspect_ratio' => $prompt->shape->aspectRatio(),
                'duration' => ($hd ? 8 : $prompt->secondsAmong([4, 6, 8])).'s',
                'resolution' => $prompt->resolution->value,
                'generate_audio' => true,
            ],
        };
    }

    /** @param Closure(): Response $request */
    private function call(Closure $request): Response
    {
        try {
            $response = $this->tags->during(AiCallTag::VideoGeneration, $request);
        } catch (Throwable $e) {
            throw new VideoGenerationFailedException('fal.ai could not be reached: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $type = collect((array) $response->json('detail'))->map(fn (mixed $item): string => is_array($item) ? (string) ($item['type'] ?? '') : '')->implode(' ');

            throw $this->failure($type, $this->errors->of($response) ?? (string) ($response->json('detail.0.msg') ?? "HTTP {$response->status()}"));
        }

        return $response;
    }

    private function failure(string $type, string $reason): VideoGenerationFailedException
    {
        return Str::contains($type, ['content_policy', 'nsfw', 'safety'])
            ? VideoGenerationFailedException::refused('fal.ai', $reason)
            : new VideoGenerationFailedException("fal.ai could not make the video: {$reason}");
    }

    private function model(?string $picked = null): string
    {
        return filled($picked) ? $picked : (string) config('services.fal.video_model');
    }
}
