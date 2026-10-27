<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Support\Http\ProviderRetry;

/**
 * Calls fal.ai models with the fal.ai key of Settings → AI providers. A
 * picture is made in one call that waits for it (`fal.run`). A video takes
 * minutes, so it goes through the queue (`queue.fal.run`): {@see submit()}
 * returns the request's path, which {@see status()} and {@see result()} take
 * back. The path names the model's app without its subpath (fal-ai/wan, not
 * fal-ai/wan/v2.2-a14b/text-to-video), as fal answers status calls only there.
 */
final readonly class FalQueue
{
    public const string ENDPOINT = 'https://queue.fal.run';

    public const string RUN_ENDPOINT = 'https://fal.run';

    /**
     * Runs a model and waits for its result.
     *
     * @param  array<string, mixed>  $input
     */
    public function run(string $model, array $input, int $timeoutSeconds): Response
    {
        return ProviderRetry::on($this->client())->timeout($timeoutSeconds)->post(self::RUN_ENDPOINT."/{$model}", $input);
    }

    /**
     * Puts a request in the model's queue.
     *
     * @param  array<string, mixed>  $input
     */
    public function submit(string $model, array $input): Response
    {
        return ProviderRetry::on($this->client())->timeout(30)->post(self::ENDPOINT."/{$model}", $input);
    }

    /**
     * The path of a queued request (`{owner}/{app}/requests/{id}`), from the
     * submit answer: its response URL when fal sent one, else the model's app.
     */
    public function requestPath(string $model, Response $submitted): string
    {
        $responseUrl = (string) $submitted->json('response_url');

        if (Str::startsWith($responseUrl, self::ENDPOINT.'/')) {
            return Str::after($responseUrl, self::ENDPOINT.'/');
        }

        $app = Str::of($model)->explode('/')->take(2)->implode('/');

        return "{$app}/requests/{$submitted->json('request_id')}";
    }

    /** IN_QUEUE, IN_PROGRESS or COMPLETED, with `error` when it failed. */
    public function status(string $requestPath): Response
    {
        return ProviderRetry::on($this->client())->timeout(30)->get(self::ENDPOINT."/{$requestPath}/status");
    }

    public function result(string $requestPath): Response
    {
        return ProviderRetry::on($this->client())->timeout(60)->get(self::ENDPOINT."/{$requestPath}");
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Key '.config('services.fal.api_key')])->acceptJson();
    }
}
