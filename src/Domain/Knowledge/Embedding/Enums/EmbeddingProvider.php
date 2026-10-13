<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Enums;

use Domain\Knowledge\Embedding\AbstractEmbeddingsProvider;
use Domain\Knowledge\Embedding\Cohere;
use Domain\Knowledge\Embedding\Gemini;
use Domain\Knowledge\Embedding\Jina;
use Domain\Knowledge\Embedding\Mistral;
use Domain\Knowledge\Embedding\OpenAi;
use Domain\Knowledge\Embedding\Qwen3;

/**
 * The services that can turn text into vectors, by the value of
 * `chatvia.ai.models.embedding` (set in the admin panel, Settings → AI). The
 * value is stored, so never rename a case value. The order of the cases is
 * the order of the pickers.
 */
enum EmbeddingProvider: string
{
    case Qwen = 'qwen';
    case OpenAi = 'openai';
    case Google = 'google';
    case Cohere = 'cohere';
    case Mistral = 'mistral';
    case Jina = 'jina';

    public function label(): string
    {
        return match ($this) {
            self::Qwen => 'Qwen3 on Scaleway AI',
            self::OpenAi => 'OpenAI',
            self::Google => 'Google Gemini',
            self::Cohere => 'Cohere',
            self::Mistral => 'Mistral',
            self::Jina => 'Jina',
        };
    }

    /**
     * The config key of its API key, so a chat provider of the same service
     * (Scaleway for Qwen3, OpenAI, Google Gemini, Mistral) can share it.
     */
    public function apiKeyConfig(): string
    {
        return match ($this) {
            self::Qwen => 'services.scalewayai.api_key',
            self::OpenAi => 'services.openai.api_key',
            self::Google => 'services.gemini.api_key',
            self::Cohere => 'services.cohere.api_key',
            self::Mistral => 'services.mistral.api_key',
            self::Jina => 'services.jina.api_key',
        };
    }

    /** The config key of the embedding model it calls. */
    public function modelConfig(): string
    {
        return match ($this) {
            self::Qwen => 'services.scalewayai.embedding_model',
            self::OpenAi => 'services.openai.embedding_model',
            self::Google => 'services.gemini.embedding_model',
            self::Cohere => 'services.cohere.embedding_model',
            self::Mistral => 'services.mistral.embedding_model',
            self::Jina => 'services.jina.embedding_model',
        };
    }

    /** Where an admin creates an API key, if the service has such a page. */
    public function keysUrl(): ?string
    {
        return match ($this) {
            self::Qwen => 'https://console.scaleway.com/iam/api-keys',
            self::OpenAi => 'https://platform.openai.com/api-keys',
            self::Google => 'https://aistudio.google.com/apikey',
            self::Cohere => 'https://dashboard.cohere.com/api-keys',
            self::Mistral => 'https://console.mistral.ai/api-keys',
            self::Jina => null,
        };
    }

    /**
     * @return class-string<AbstractEmbeddingsProvider>
     */
    public function providerClass(): string
    {
        return match ($this) {
            self::Qwen => Qwen3::class,
            self::OpenAi => OpenAi::class,
            self::Google => Gemini::class,
            self::Cohere => Cohere::class,
            self::Mistral => Mistral::class,
            self::Jina => Jina::class,
        };
    }
}
