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
 * and the default one, the model for quick tasks (null: they use the
 * default chat model), the model for image descriptions (null: image
 * descriptions are off), the model for chat file uploads (null: chat file
 * uploads are off), the embedding service and model (with its account's
 * key when that is another account), the reranker, the document reader,
 * the Firecrawl key for the web scraper, speech to text, voice
 * conversations, where files are kept, how email is sent, whether
 * Spectra records AI calls, whether AI enhances what agents learn (the
 * parts of files, web pages and texts, and the summary of each source),
 * and the app's name.
 *
 * A null key (or endpoint, for Azure OpenAI) keeps the saved one. A null
 * embedding provider keeps the one in use (sources are trained with it
 * already); a null embedding model the saved one. A null reader, speech or
 * voice service turns that service off ("Disable …"). A null reranker,
 * storage, mail or app name leaves those settings as they are.
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
        public ?string $chatEndpoint = null,
        public ?RemoteModelData $quickTaskModel = null,
        public ?RemoteModelData $visionModel = null,
        public ?RemoteModelData $filesModel = null,
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
        public ?AiProvider $voice = null,
        public ?string $voiceApiKey = null,
        public ?StorageSetupData $storage = null,
        public ?MailSetupData $mail = null,
        public bool $recordAiCalls = true,
        public ?string $appName = null,
        public bool $enhanceKnowledge = false,
    ) {}
}
