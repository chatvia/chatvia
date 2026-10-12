<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Enums;

use Domain\Knowledge\Embedding\AbstractEmbeddingsProvider;
use Domain\Knowledge\Embedding\Cohere;
use Domain\Knowledge\Embedding\Jina;
use Domain\Knowledge\Embedding\Mistral;
use Domain\Knowledge\Embedding\Qwen3;

/**
 * The services that can turn text into vectors, by the value of
 * `chatvia.ai.models.embedding` (set in the admin panel, Settings → AI). The
 * value is stored, so never rename a case value.
 */
enum EmbeddingProvider: string
{
    case Qwen = 'qwen';
    case Cohere = 'cohere';
    case Jina = 'jina';
    case Mistral = 'mistral';

    public function label(): string
    {
        return match ($this) {
            self::Qwen => 'Qwen3 on Scaleway AI',
            self::Cohere => 'Cohere',
            self::Jina => 'Jina',
            self::Mistral => 'Mistral',
        };
    }

    /**
     * The config key of its API key, so a chat provider of the same service
     * (Scaleway for Qwen3, Mistral) can share it.
     */
    public function apiKeyConfig(): string
    {
        return match ($this) {
            self::Qwen => 'services.scalewayai.api_key',
            self::Cohere => 'services.cohere.api_key',
            self::Jina => 'services.jina.api_key',
            self::Mistral => 'services.mistral.api_key',
        };
    }

    /**
     * @return class-string<AbstractEmbeddingsProvider>
     */
    public function providerClass(): string
    {
        return match ($this) {
            self::Qwen => Qwen3::class,
            self::Cohere => Cohere::class,
            self::Jina => Jina::class,
            self::Mistral => Mistral::class,
        };
    }
}
