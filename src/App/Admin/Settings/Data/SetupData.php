<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderEnum;
use Spatie\LaravelData\Data;

/**
 * What the admin setup of a new install collects: the chat provider (its
 * kind, its own address if any, and key) and the model agents start with, the search
 * (embedding) service and its key, and how email is sent.
 *
 * A null embedding provider keeps the one in use (sources are trained with
 * it already); a null key reuses the chat provider's key for the same
 * service. No mail means email stays as it is.
 */
final class SetupData extends Data
{
    public function __construct(
        public LlmProviderEnum $chatKind,
        public ?string $chatBaseUrl,
        public ?string $chatApiKey,
        public RemoteModelData $chatModel,
        public ?EmbeddingProvider $embeddingProvider = null,
        public ?string $embeddingApiKey = null,
        public ?SetupMailData $mail = null,
    ) {}
}
