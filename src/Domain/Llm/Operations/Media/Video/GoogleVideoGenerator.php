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
use Domain\Llm\Services\ProviderErrorReason;
use Domain\Llm\Services\VideoDownloader;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Support\Http\ProviderRetry;
use Throwable;

/**
 * Videos through the Gemini API, with the Google key and address of
 * Settings → AI providers and the model of `services.gemini.video_model`.
 * Gemini Omni Flash (the default) runs as a background interaction that is
 * deleted once its video is saved; Veo 3 (deprecated by Google, offered while
 * it serves it) runs as a long-running operation. The model list says which
 * API each one takes ({@see MODELS}), so no model id is guessed at. A
 * prompt or a video Google's safety filters block is a refusal.
 */
class GoogleVideoGenerator implements VideoGenerator
{
    /** The offered models, and the API each one runs on. */
    public const array MODELS = [
        'gemini-omni-1.1-flash' => 'interactions',
        'veo-3.0-generate-001' => 'veo',
        'veo-3.0-fast-generate-001' => 'veo',
    ];

    /** Words Google's errors use when its rules blocked the prompt or the video. */
    private const array REFUSALS = ['safety', 'blocked', 'prohibited', 'responsible ai', 'usage guidelines', 'policy'];

    public function __construct(
        private readonly AiCallTags $tags,
        private readonly ProviderErrorReason $errors,
        private readonly VideoDownloader $downloads,
    ) {}

    public function start(VideoPromptData $prompt): string
    {
        $model = $this->model($prompt->model);
        $interactions = (self::MODELS[$model] ?? 'veo') === 'interactions';

        $response = $this->call(fn (PendingRequest $google): Response => $interactions
            ? $google->post("{$this->endpoint()}/interactions", [
                'model' => $model,
                // Omni takes no length: it follows the one the prompt asks for.
                'input' => "{$prompt->prompt}\n\nLength: about {$prompt->secondsBetween(3, 10)} seconds.",
                'background' => true,
                'store' => true,
                'response_format' => [
                    'type' => 'video',
                    'aspect_ratio' => $prompt->shape->aspectRatio(),
                    'resolution' => $prompt->resolution->value,
                ],
            ])
            : $google->post("{$this->endpoint()}/models/".Str::chopStart($model, 'models/').':predictLongRunning', [
                'instances' => [['prompt' => $prompt->prompt]],
                'parameters' => [
                    'aspectRatio' => $prompt->shape->aspectRatio(),
                    'durationSeconds' => 8,
                    // Veo 3 makes 1080p only in landscape.
                    'resolution' => $prompt->shape === VideoShape::Portrait ? VideoResolution::Hd720->value : $prompt->resolution->value,
                ],
            ]));

        $id = $interactions ? $response->json('id') : $response->json('name');

        if (blank($id)) {
            throw new VideoGenerationFailedException('Google did not start the video.');
        }

        return $interactions ? "interactions/{$id}" : (string) $id;
    }

    public function check(string $taskId): ?GeneratedVideoData
    {
        $response = $this->call(fn (PendingRequest $google): Response => $google->get("{$this->endpoint()}/{$taskId}"));

        return Str::startsWith($taskId, 'interactions/') ? $this->interaction($response) : $this->operation($response);
    }

    public function forget(string $taskId): void
    {
        if (! Str::startsWith($taskId, 'interactions/')) {
            return;
        }

        rescue(fn () => $this->client()->timeout(15)->delete("{$this->endpoint()}/{$taskId}"), report: false);
    }

    /** A background interaction: the video is a `video` part of its output once it is completed. */
    private function interaction(Response $response): ?GeneratedVideoData
    {
        $status = (string) $response->json('status');

        if (collect(['failed', 'cancelled', 'incomplete'])->containsStrict($status)) {
            throw $this->failure((string) ($response->json('error.message') ?? $status));
        }

        if ($status !== 'completed') {
            return null;
        }

        $video = collect([...(array) $response->json('steps'), ...(array) $response->json('outputs')])
            ->flatMap(fn (mixed $item): array => is_array($item) ? [$item, ...(array) ($item['content'] ?? [])] : [])
            ->first(fn (mixed $part): bool => is_array($part) && ($part['type'] ?? null) === 'video' && (filled($part['data'] ?? null) || filled($part['uri'] ?? null)));

        if (! is_array($video)) {
            throw new VideoGenerationFailedException('Google sent no video.');
        }

        return filled($video['data'] ?? null)
            ? $this->downloads->fromBase64((string) $video['data'], (string) ($video['mime_type'] ?? 'video/mp4'), 'Google')
            : $this->downloads->fromUrl((string) $video['uri'], 'Google', $this->keyHeader());
    }

    /** A Veo operation: done, with the video's link in its response, or an error. */
    private function operation(Response $response): ?GeneratedVideoData
    {
        if (! $response->json('done')) {
            return null;
        }

        if (filled($response->json('error'))) {
            throw $this->failure((string) $response->json('error.message'));
        }

        $uri = (string) $response->json('response.generateVideoResponse.generatedSamples.0.video.uri');

        if (blank($uri)) {
            $filtered = (array) $response->json('response.generateVideoResponse.raiMediaFilteredReasons');

            throw filled($filtered) || (int) $response->json('response.generateVideoResponse.raiMediaFilteredCount') > 0
                ? VideoGenerationFailedException::refused('Google', (string) ($filtered[0] ?? ''))
                : new VideoGenerationFailedException('Google sent no video.');
        }

        return $this->downloads->fromUrl($uri, 'Google', $this->keyHeader());
    }

    /**
     * One call to Google; an answer that is not a success is a failure, or a
     * refusal when Google's rules blocked it.
     *
     * @param  callable(PendingRequest): Response  $request
     */
    private function call(callable $request): Response
    {
        try {
            $response = $this->tags->during(AiCallTag::VideoGeneration, fn (): Response => $request(ProviderRetry::on($this->client())->timeout(60)));
        } catch (Throwable $e) {
            throw new VideoGenerationFailedException("Google could not be reached: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw $this->failure($this->errors->of($response) ?? "HTTP {$response->status()}");
        }

        return $response;
    }

    private function failure(string $reason): VideoGenerationFailedException
    {
        return Str::contains(Str::lower($reason), self::REFUSALS)
            ? VideoGenerationFailedException::refused('Google', $reason)
            : new VideoGenerationFailedException("Google could not make the video: {$reason}");
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders($this->keyHeader())->acceptJson();
    }

    /** @return array<string, string> */
    private function keyHeader(): array
    {
        return ['x-goog-api-key' => (string) config('services.gemini.api_key')];
    }

    private function endpoint(): string
    {
        return Str::rtrim((string) config('services.gemini.api_endpoint'), '/');
    }

    private function model(?string $picked = null): string
    {
        return filled($picked) ? $picked : (string) config('services.gemini.video_model');
    }
}
