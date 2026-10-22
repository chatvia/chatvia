<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Settings\Data\ConnectionCheckData;
use App\Admin\Settings\Data\RerankerSetupData;
use Domain\Knowledge\Embedding\Rerank\RerankerFactory;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Services\AiCallTags;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * Checks a reranker with a test question and two short texts, with the key,
 * model and address typed (or else the saved ones). A real call is the only
 * way to know the account may use that model: a key that reads the model
 * list can still be refused for reranking, or have no credit left. The call
 * is tagged `service-check` in Spectra, on top of `rerank`.
 */
final readonly class RerankerCheck
{
    public function __construct(
        private RerankerFactory $factory,
        private AiCallTags $tags,
    ) {}

    public function check(RerankerSetupData $reranker): ConnectionCheckData
    {
        if (! $reranker->isOn()) {
            return new ConnectionCheckData(true);
        }

        $name = match ($reranker->driver) {
            'openrouter' => 'OpenRouter',
            'cohere' => 'Cohere',
            default => 'Your reranker server',
        };

        try {
            $this->tags->during(AiCallTag::ServiceCheck, fn (): array => $this->factory
                ->draft($reranker->driver, $reranker->apiKey, $reranker->model, $reranker->url)
                ->rerank('Where is the office?', ['The office is in the city centre.', 'Lunch is at noon.'], 1));
        } catch (ConnectionException) {
            return new ConnectionCheckData(false, message: "{$name} could not be reached.");
        } catch (RequestException $exception) {
            $status = $exception->response->status();

            if (in_array($status, [401, 403], true)) {
                return new ConnectionCheckData(false, keyRefused: true, message: "{$name} refused this key. Check that you copied all of it.");
            }

            $detail = $exception->response->json('error.message') ?? $exception->response->json('message');

            return new ConnectionCheckData(false, message: "{$name} could not rerank with this model (error {$status})".(filled($detail) ? ": {$detail}" : '.'));
        } catch (RuntimeException $exception) {
            return new ConnectionCheckData(false, message: $exception->getMessage());
        }

        return new ConnectionCheckData(true);
    }
}
