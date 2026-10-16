<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Llm\Data\Catalog\CustomLlmServerData;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * What the admin setup of a new install collects: the chat provider (an
 * account's key, or a server of your own), the chat models agents can use
 * and the default one, the embedding service and model (with its account's
 * key when that is another account), the reranker, the document reader,
 * the Firecrawl key for website import, speech to text, where files are
 * kept, and how email is sent.
 *
 * A null key keeps the saved one. A null embedding provider keeps the one
 * in use (sources are trained with it already); a null embedding model the
 * saved one. A null reader or speech service turns that service off ("Not
 * now"). A null reranker, storage or mail leaves those settings as they are.
 */
final class SetupData extends Data
{
    /**
     * @param  list<RemoteModelData>  $chatModels  the models agents can use, at least one
     */
    public function __construct(
        public LlmProviderKind $chatKind,
        public ?string $apiKey,
        #[DataCollectionOf(RemoteModelData::class)]
        public array $chatModels,
        public string $defaultModel,
        public ?CustomLlmServerData $server = null,
        public ?EmbeddingProvider $embeddingProvider = null,
        public ?string $embeddingModel = null,
        public ?string $embeddingApiKey = null,
        public ?RerankerSetupData $reranker = null,
        public ?AiProvider $documentReader = null,
        public ?string $documentApiKey = null,
        public ?string $documentEndpoint = null,
        public ?string $firecrawlApiKey = null,
        public ?AiProvider $transcriber = null,
        public ?string $transcriptionApiKey = null,
        public ?StorageSetupData $storage = null,
        public ?MailSetupData $mail = null,
    ) {}
}
