<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderEnum;
use Spatie\LaravelData\Data;

/**
 * What the admin setup of a new install collects: whether knowledge search
 * is hybrid (meaning plus keywords), the search (embedding) service with its
 * model and key, then the chat provider (its kind, its own address if any,
 * and key) and the model agents start with.
 *
 * A null embedding provider keeps the one in use (sources are trained with
 * it already). A null embedding model or key keeps the saved one.
 */
final class SetupData extends Data
{
    public function __construct(
        public LlmProviderEnum $chatKind,
        public ?string $chatBaseUrl,
        public ?string $chatApiKey,
        public RemoteModelData $chatModel,
        public bool $hybridSearch = true,
        public ?EmbeddingProvider $embeddingProvider = null,
        public ?string $embeddingModel = null,
        public ?string $embeddingApiKey = null,
    ) {}
}
