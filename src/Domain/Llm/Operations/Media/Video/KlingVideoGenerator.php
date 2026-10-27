<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media\Video;

use Domain\Llm\Contracts\VideoGenerator;
use Domain\Llm\Data\Video\GeneratedVideoData;
use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Enums\AiCallTag;
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
 * Videos through Kling's API (`/text-to-video/{model}`, the model in the
 * path), with the Kling key and address of Settings → AI providers and the
 * model of `services.kling.video_model` (Kling 3.0 Turbo by default). A task
 * is looked up by its id (`/tasks`). A prompt Kling's content rules block
 * (codes 1300 and 1301, or a failed task that names its risk control) is a
 * refusal. Kling keeps made videos for 30 days.
 */
class KlingVideoGenerator implements VideoGenerator
{
    /** The offered models and the lengths each makes, in seconds. */
    public const array MODELS = [
        'kling-3.0-turbo' => [3, 15],
        'kling-4.0' => [3, 30],
    ];

    /** Kling's codes for a request its platform or content rules block. */
    private const array REFUSAL_CODES = [1300, 1301];

    public function __construct(
        private readonly AiCallTags $tags,
        private readonly VideoDownloader $downloads,
    ) {}

    public function start(VideoPromptData $prompt): string
    {
        $model = $this->model($prompt->model);

        $response = $this->call(fn (PendingRequest $kling): Response => $kling->post("{$this->endpoint()}/text-to-video/{$model}", [
            'prompt' => $prompt->prompt,
            'settings' => [
                'aspect_ratio' => $prompt->shape->aspectRatio(),
                'duration' => $prompt->secondsBetween(...(self::MODELS[$model] ?? self::MODELS['kling-3.0-turbo'])),
                'resolution' => $prompt->resolution->value,
            ],
        ]));

        return (string) ($response->json('data.id') ?? throw new VideoGenerationFailedException('Kling did not start the video.'));
    }

    public function check(string $taskId): ?GeneratedVideoData
    {
        $response = $this->call(fn (PendingRequest $kling): Response => $kling->get("{$this->endpoint()}/tasks", ['task_ids' => $taskId]));
        $task = collect((array) $response->json('data'))->first(fn (mixed $task): bool => is_array($task) && (string) ($task['id'] ?? '') === $taskId);

        if (! is_array($task)) {
            return null;
        }

        if (($task['status'] ?? null) === 'failed') {
            $reason = (string) ($task['message'] ?? '');

            throw Str::contains(Str::lower($reason), ['risk', 'security', 'policy'])
                ? VideoGenerationFailedException::refused('Kling', $reason)
                : new VideoGenerationFailedException("Kling could not make the video: {$reason}");
        }

        if (($task['status'] ?? null) !== 'succeeded') {
            return null;
        }

        $video = collect((array) ($task['outputs'] ?? []))->first(fn (mixed $output): bool => is_array($output) && ($output['type'] ?? 'video') === 'video' && filled($output['url'] ?? null));

        return is_array($video)
            ? $this->downloads->fromUrl((string) $video['url'], 'Kling')
            : throw new VideoGenerationFailedException('Kling sent no video.');
    }

    public function forget(string $taskId): void {}

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(?string $model = null): string
    {
        return "kling {$this->model($model)}";
    }

    /**
     * One call to Kling. Its answers carry a `code` (0 is success) besides
     * the HTTP status.
     *
     * @param  callable(PendingRequest): Response  $request
     */
    private function call(callable $request): Response
    {
        try {
            $response = $this->tags->during(AiCallTag::VideoGeneration, fn (): Response => $request(
                ProviderRetry::on(Http::withToken((string) config('services.kling.api_key'))->acceptJson()->timeout(60)),
            ));
        } catch (Throwable $e) {
            throw new VideoGenerationFailedException("Kling could not be reached: {$e->getMessage()}", previous: $e);
        }

        $code = (int) $response->json('code', 0);

        if ($response->failed() || $code !== 0) {
            $reason = (string) ($response->json('message') ?? "HTTP {$response->status()}");

            throw collect(self::REFUSAL_CODES)->containsStrict($code)
                ? VideoGenerationFailedException::refused('Kling', $reason)
                : new VideoGenerationFailedException("Kling could not make the video: {$reason}");
        }

        return $response;
    }

    private function endpoint(): string
    {
        return Str::rtrim((string) config('services.kling.endpoint'), '/');
    }

    private function model(?string $picked = null): string
    {
        return filled($picked) ? $picked : (string) config('services.kling.video_model');
    }
}
