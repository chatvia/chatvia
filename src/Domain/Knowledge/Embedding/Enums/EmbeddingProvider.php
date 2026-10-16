<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Enums;

use Domain\Knowledge\Embedding\AbstractEmbeddingsProvider;
use Domain\Knowledge\Embedding\Cohere;
use Domain\Knowledge\Embedding\Data\RelevanceThresholdsData;
use Domain\Knowledge\Embedding\Gemini;
use Domain\Knowledge\Embedding\Jina;
use Domain\Knowledge\Embedding\Mistral;
use Domain\Knowledge\Embedding\OpenAi;
use Domain\Knowledge\Embedding\OpenRouter;
use Domain\Knowledge\Embedding\Qwen3;
use Domain\Llm\Enums\AiProvider;

/**
 * The services that can turn text into vectors, by the value of
 * `chatvia.ai.models.embedding` (set in the admin panel, Settings → AI models). The
 * value is stored, so never rename a case value. The order of the cases is
 * the order of the pickers.
 */
enum EmbeddingProvider: string
{
    case Qwen = 'qwen';
    case OpenAi = 'openai';
    case OpenRouter = 'openrouter';
    case Google = 'google';
    case Cohere = 'cohere';
    case Mistral = 'mistral';
    case Jina = 'jina';

    /** The provider set in config (`chatvia.ai.models.embedding`), if it is a known one. */
    public static function configured(): ?self
    {
        return self::tryFrom((string) config('chatvia.ai.models.embedding'));
    }

    /**
     * The cosine similarities a chunk or a tool must reach when no reranker
     * is set up (the basic similarity check), measured with the evals in
     * tests/Eval for this provider's default model. Null when never measured:
     * then nothing is gated, as a wrong bar would drop good answers.
     */
    public function similarityThresholds(): ?RelevanceThresholdsData
    {
        return match ($this) {
            self::Qwen => new RelevanceThresholdsData(knowledge: 0.35, toolRead: 0.35, toolWrite: 0.40),
            self::OpenAi, self::OpenRouter, self::Google,
            self::Cohere, self::Mistral, self::Jina => null,
        };
    }

    /**
     * The cosine similarity a visitor's message must reach to a saved Q&A
     * question to get its answer by meaning. High on purpose: a wrong saved
     * answer is sent word for word. Null when never measured: then Q&A
     * answers match only as a whole question.
     */
    public function qaSimilarityThreshold(): ?float
    {
        return match ($this) {
            // The highest bar that still matches clear paraphrases; it blocks
            // every near-miss in the eval, including a cross-language wrong
            // match at 0.64 (qa.php's sweep — 0.80 matched nothing at all).
            self::Qwen => 0.65,
            self::OpenAi, self::OpenRouter, self::Google,
            self::Cohere, self::Mistral, self::Jina => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Qwen => 'Qwen3 on Scaleway AI',
            self::OpenAi => 'OpenAI',
            self::OpenRouter => 'OpenRouter',
            self::Google => 'Google Gemini',
            self::Cohere => 'Cohere',
            self::Mistral => 'Mistral',
            self::Jina => 'Jina',
        };
    }

    /** The AI provider account whose key it calls with (Settings → AI providers). */
    public function account(): AiProvider
    {
        return match ($this) {
            self::Qwen => AiProvider::Scaleway,
            self::OpenAi => AiProvider::OpenAi,
            self::OpenRouter => AiProvider::OpenRouter,
            self::Google => AiProvider::Google,
            self::Cohere => AiProvider::Cohere,
            self::Mistral => AiProvider::Mistral,
            self::Jina => AiProvider::Jina,
        };
    }

    /** The config key of its API key: its account's. */
    public function apiKeyConfig(): string
    {
        return $this->account()->apiKeyConfig();
    }

    /** The config key of the embedding model it calls. */
    public function modelConfig(): string
    {
        return match ($this) {
            self::Qwen => 'services.scalewayai.embedding_model',
            self::OpenAi => 'services.openai.embedding_model',
            self::OpenRouter => 'services.openrouter.embedding_model',
            self::Google => 'services.gemini.embedding_model',
            self::Cohere => 'services.cohere.embedding_model',
            self::Mistral => 'services.mistral.embedding_model',
            self::Jina => 'services.jina.embedding_model',
        };
    }

    /** Where an admin creates an API key. */
    public function keysUrl(): string
    {
        return $this->account()->keysUrl();
    }

    /**
     * The providers that return vectors of the size this install stores
     * (chosen when it was installed), in order.
     *
     * @return list<self>
     */
    public static function fitting(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $provider): bool => app($provider->providerClass())->fitsStorage(),
        ));
    }

    /** The first provider that fits the stored size and runs on an account. */
    public static function fittingFor(AiProvider $account): ?self
    {
        return array_find(self::fitting(), fn (self $provider): bool => $provider->account() === $account);
    }

    /**
     * @return class-string<AbstractEmbeddingsProvider>
     */
    public function providerClass(): string
    {
        return match ($this) {
            self::Qwen => Qwen3::class,
            self::OpenAi => OpenAi::class,
            self::OpenRouter => OpenRouter::class,
            self::Google => Gemini::class,
            self::Cohere => Cohere::class,
            self::Mistral => Mistral::class,
            self::Jina => Jina::class,
        };
    }
}
