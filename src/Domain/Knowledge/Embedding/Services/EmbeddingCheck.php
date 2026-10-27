<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Services;

use Closure;
use Domain\Knowledge\Embedding\Data\EmbeddingCheckData;
use Domain\Knowledge\Embedding\Enums\EmbeddingCheckStatus;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Services\AiCallTags;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;

/**
 * Embeds a short text with an embedding service, as training a source will:
 * with a key and model typed but not saved yet, or the saved ones. It checks
 * the key, the model, and (when given) the vector size the database stores.
 * The call is tagged `service-check` in Spectra, apart from real traffic.
 */
final readonly class EmbeddingCheck
{
    /** The text embedded to check a service. */
    private const string TEXT = 'How do I reset my password?';

    public function __construct(
        private AiCallTags $tags,
        private Container $container,
    ) {}

    public function check(EmbeddingProvider $provider, ?string $model = null, ?string $apiKey = null, ?int $dimensions = null): EmbeddingCheckData
    {
        $values = array_filter([
            $provider->apiKeyConfig() => $apiKey,
            $provider->modelConfig() => $model !== null ? Str::trim($model) : null,
        ], filled(...));

        try {
            $returned = $this->tags->during(AiCallTag::ServiceCheck, fn (): int => $this->withConfig($values, fn (): int => count((array) $this->container->make($provider->providerClass())->embed(self::TEXT))));
        } catch (RequestException $e) {
            $status = $e->response->status();

            return new EmbeddingCheckData(collect([401, 403])->containsStrict($status) ? EmbeddingCheckStatus::KeyRefused : EmbeddingCheckStatus::ModelFailed, httpStatus: $status);
        } catch (ConnectionException) {
            return new EmbeddingCheckData(EmbeddingCheckStatus::Unreachable);
        }

        return match (true) {
            $returned === 0 => new EmbeddingCheckData(EmbeddingCheckStatus::Empty, 0),
            $dimensions !== null && $returned !== $dimensions => new EmbeddingCheckData(EmbeddingCheckStatus::WrongSize, $returned),
            default => new EmbeddingCheckData(EmbeddingCheckStatus::Ok, $returned),
        };
    }

    /**
     * Run a callback with some config values in place (the typed key and
     * model, which the embedding services read from config), then put the
     * old values back.
     *
     * @template T
     *
     * @param  array<string, string>  $values
     * @param  Closure(): T  $callback
     * @return T
     */
    private function withConfig(array $values, Closure $callback): mixed
    {
        $before = array_map(fn (string $key): mixed => config($key), array_combine(array_keys($values), array_keys($values)));

        config($values);

        try {
            return $callback();
        } finally {
            config($before);
        }
    }
}
