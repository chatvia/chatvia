<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media\Video;

use Domain\Llm\Contracts\VideoGenerator;
use Domain\Llm\Data\Video\GeneratedVideoData;
use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Enums\VideoShape;
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
 * Videos through Runway's API (`/text_to_video`, then `/tasks/{id}`), with
 * the Runway key of Settings → AI providers and the model of
 * `services.runway.video_model` (Gen-4.5 by default; Runway also serves
 * Veo, Kling and Seedance). Runway names the shape by pixel size and bills
 * in credits per second. A task its moderation stops (a `SAFETY` failure
 * code) is a refusal. The task is deleted once its video is saved.
 */
class RunwayVideoGenerator implements VideoGenerator
{
    public const string ENDPOINT = 'https://api.dev.runwayml.com/v1';

    /** The API version every call names (`X-Runway-Version`). */
    public const string API_VERSION = '2024-11-06';

    /** Runway takes at most this many characters of prompt. */
    private const int MAX_PROMPT = 1000;

    /**
     * The offered models: the lengths each makes (a few to choose from, or a
     * range), and its landscape size at 720p and 1080p (null when it makes
     * only one). Portrait swaps the sides.
     */
    public const array MODELS = [
        'gen4.5' => ['between' => [2, 10], 'sizes' => ['1280:720', null]],
        'veo3.1' => ['choices' => [4, 6, 8], 'sizes' => ['1280:720', '1920:1080']],
        'veo3.1_fast' => ['choices' => [4, 6, 8], 'sizes' => ['1280:720', '1920:1080']],
        'kling3.0_pro' => ['between' => [3, 15], 'sizes' => [null, '1920:1080']],
        'seedance2' => ['between' => [4, 15], 'sizes' => ['1280:720', '1920:1080']],
    ];

    public function __construct(
        private readonly AiCallTags $tags,
        private readonly VideoDownloader $downloads,
    ) {}

    public function start(VideoPromptData $prompt): string
    {
        $model = $this->model($prompt->model);
        $facts = self::MODELS[$model] ?? self::MODELS['gen4.5'];

        $response = $this->call(fn (PendingRequest $runway): Response => $runway->post(self::ENDPOINT.'/text_to_video', [
            'model' => $model,
            'promptText' => Str::limit($prompt->prompt, self::MAX_PROMPT, ''),
            'ratio' => $this->ratio($facts['sizes'], $prompt),
            'duration' => isset($facts['between']) ? $prompt->secondsBetween(...$facts['between']) : $prompt->secondsAmong($facts['choices']),
        ]));

        return (string) ($response->json('id') ?? throw new VideoGenerationFailedException('Runway did not start the video.'));
    }

    public function check(string $taskId): ?GeneratedVideoData
    {
        $response = $this->call(fn (PendingRequest $runway): Response => $runway->get(self::ENDPOINT.'/tasks/'.rawurlencode($taskId)));

        return match ((string) $response->json('status')) {
            'SUCCEEDED' => filled($response->json('output.0'))
                ? $this->downloads->fromUrl((string) $response->json('output.0'), 'Runway')
                : throw new VideoGenerationFailedException('Runway sent no video.'),
            'FAILED', 'CANCELLED' => throw Str::startsWith((string) $response->json('failureCode'), 'SAFETY')
                ? VideoGenerationFailedException::refused('Runway', (string) $response->json('failure'))
                : new VideoGenerationFailedException('Runway could not make the video: '.($response->json('failure') ?? $response->json('status'))),
            default => null,
        };
    }

    public function forget(string $taskId): void
    {
        rescue(fn () => $this->client()->timeout(15)->delete(self::ENDPOINT.'/tasks/'.rawurlencode($taskId)), report: false);
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(?string $model = null): string
    {
        return 'runway '.$this->model($model);
    }

    /**
     * The pixel size: the one at the resolution asked for when the model
     * makes it, else the one it makes; portrait swaps the sides.
     *
     * @param  array{?string, ?string}  $sizes
     */
    private function ratio(array $sizes, VideoPromptData $prompt): string
    {
        [$sd, $hd] = $sizes;
        $size = (string) ($prompt->resolution === VideoResolution::Hd1080 ? ($hd ?? $sd) : ($sd ?? $hd));

        return $prompt->shape === VideoShape::Portrait
            ? Str::afterLast($size, ':').':'.Str::before($size, ':')
            : $size;
    }

    /** @param callable(PendingRequest): Response $request */
    private function call(callable $request): Response
    {
        try {
            $response = $this->tags->during(AiCallTag::VideoGeneration, fn (): Response => $request(ProviderRetry::on($this->client())->timeout(60)));
        } catch (Throwable $e) {
            throw new VideoGenerationFailedException('Runway could not be reached: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $error = $response->json('error');
            $reason = is_array($error) ? ($error['message'] ?? null) : $error;

            throw new VideoGenerationFailedException('Runway could not make the video: '.(filled($reason) && is_scalar($reason) ? $reason : "HTTP {$response->status()}"));
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('services.runway.api_key'))
            ->withHeaders(['X-Runway-Version' => self::API_VERSION])
            ->acceptJson();
    }

    private function model(?string $picked = null): string
    {
        return filled($picked) ? $picked : (string) config('services.runway.video_model');
    }
}
