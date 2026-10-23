<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

use Domain\Llm\Services\ModelPresets;

/**
 * An AI provider account: one API key (Settings → AI providers) that serves
 * everything the provider does here, chat models and the other services
 * alike. Chat provider rows of the same kind hold no key of their own; they
 * call with this one (see the adapters' `apiKey()`). A server of your own
 * is not an account: its row keeps its address and key.
 *
 * The order of the cases is the order "Automatic" tries them in: speech to
 * text uses Scaleway AI before OpenAI, reranking OpenRouter before Cohere,
 * document reading Mistral before Azure.
 */
enum AiProvider: string
{
    case Scaleway = 'scaleway';
    case OpenRouter = 'openrouter';
    case OpenAi = 'openai';
    case Mistral = 'mistral';
    case Google = 'google';
    case Groq = 'groq';
    case Anthropic = 'anthropic';
    case Cohere = 'cohere';
    case Jina = 'jina';
    case Voyage = 'voyage';
    case Azure = 'azure';

    /**
     * @return list<AiCapability>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::Scaleway => [AiCapability::Chat, AiCapability::Embeddings, AiCapability::Speech],
            self::OpenRouter => [AiCapability::Chat, AiCapability::Embeddings, AiCapability::Reranking],
            self::OpenAi => [AiCapability::Chat, AiCapability::Embeddings, AiCapability::Speech, AiCapability::Voice],
            self::Mistral => [AiCapability::Chat, AiCapability::Embeddings, AiCapability::Documents],
            self::Google => [AiCapability::Chat, AiCapability::Embeddings],
            self::Groq => [AiCapability::Chat],
            self::Anthropic => [AiCapability::Chat],
            self::Cohere => [AiCapability::Embeddings, AiCapability::Reranking],
            self::Jina => [AiCapability::Embeddings],
            self::Voyage => [AiCapability::Embeddings],
            self::Azure => [AiCapability::Documents],
        };
    }

    public function can(AiCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Scaleway => 'Scaleway AI',
            self::OpenRouter => 'OpenRouter',
            self::OpenAi => 'OpenAI',
            self::Mistral => 'Mistral',
            self::Google => 'Google Gemini',
            self::Groq => 'Groq',
            self::Anthropic => 'Anthropic',
            self::Cohere => 'Cohere',
            self::Jina => 'Jina',
            self::Voyage => 'Voyage AI',
            self::Azure => 'Azure Document Intelligence',
        };
    }

    /** One short sentence for the provider pickers. */
    public function description(): string
    {
        return match ($this) {
            self::Scaleway => 'Open models hosted in Europe.',
            self::OpenRouter => 'Hundreds of models from many companies, with one key.',
            self::OpenAi => 'The GPT models.',
            self::Mistral => 'Mistral\'s models, and reading of documents and PDFs.',
            self::Google => 'The Gemini models.',
            self::Groq => 'Open models with very fast answers.',
            self::Anthropic => 'The Claude models.',
            self::Cohere => 'Embeddings and reranking.',
            self::Jina => 'Embeddings.',
            self::Voyage => 'Embeddings made for search.',
            self::Azure => 'Reads PDFs, Word, PowerPoint and images, with their tables. Needs the standard tier (S0): the free one reads 2 pages of each file.',
        };
    }

    /** The config key of its API key. */
    public function apiKeyConfig(): string
    {
        return match ($this) {
            self::Scaleway => 'services.scalewayai.api_key',
            self::OpenRouter => 'services.openrouter.api_key',
            self::OpenAi => 'services.openai.api_key',
            self::Mistral => 'services.mistral.api_key',
            self::Google => 'services.gemini.api_key',
            self::Groq => 'services.groq.api_key',
            self::Anthropic => 'services.anthropic.api_key',
            self::Cohere => 'services.cohere.api_key',
            self::Jina => 'services.jina.api_key',
            self::Voyage => 'services.voyage.api_key',
            self::Azure => 'services.azure_document_intelligence.key',
        };
    }

    /** The config key of its API address, for providers whose address can change. */
    public function baseUrlConfig(): ?string
    {
        return match ($this) {
            self::Scaleway => 'services.scalewayai.endpoint',
            self::OpenAi => 'services.openai.api_endpoint',
            self::Mistral => 'services.mistral.base_url',
            self::Google => 'services.gemini.api_endpoint',
            self::Cohere => 'services.cohere.api_endpoint',
            self::Jina => 'services.jina.api_endpoint',
            self::Voyage => 'services.voyage.api_endpoint',
            self::Azure => 'services.azure_document_intelligence.endpoint',
            self::OpenRouter, self::Groq, self::Anthropic => null,
        };
    }

    /** Where an admin creates an API key. */
    public function keysUrl(): string
    {
        return match ($this) {
            self::Scaleway => 'https://console.scaleway.com/iam/api-keys',
            self::OpenRouter => 'https://openrouter.ai/settings/keys',
            self::OpenAi => 'https://platform.openai.com/api-keys',
            self::Mistral => 'https://console.mistral.ai/api-keys',
            self::Google => 'https://aistudio.google.com/apikey',
            self::Groq => 'https://console.groq.com/keys',
            self::Anthropic => 'https://console.anthropic.com/settings/keys',
            self::Cohere => 'https://dashboard.cohere.com/api-keys',
            self::Jina => 'https://jina.ai/api-dashboard/key-manager',
            self::Voyage => 'https://dashboard.voyageai.com/organization/api-keys',
            self::Azure => 'https://portal.azure.com/#view/Microsoft_Azure_ProjectOxford/CognitiveServicesHub/~/DocumentIntelligence',
        };
    }

    /** The kind of its chat provider rows, or null when it has no chat models. */
    public function chatKind(): ?LlmProviderKind
    {
        return match ($this) {
            self::Scaleway => LlmProviderKind::Scaleway,
            self::OpenRouter => LlmProviderKind::OpenRouter,
            self::OpenAi => LlmProviderKind::OpenAi,
            self::Mistral => LlmProviderKind::Mistral,
            self::Google => LlmProviderKind::Google,
            self::Groq => LlmProviderKind::Groq,
            self::Anthropic => LlmProviderKind::Anthropic,
            self::Cohere, self::Jina, self::Voyage, self::Azure => null,
        };
    }

    /** The account of a chat provider kind, or null for a server of your own. */
    public static function forChatKind(LlmProviderKind $kind): ?self
    {
        return array_find(self::cases(), fn (self $provider): bool => $provider->chatKind() === $kind);
    }

    /** Whether its API key is set, and its address when it has no default (Azure). */
    public function isConnected(): bool
    {
        return filled(config($this->apiKeyConfig()))
            && (! $this->needsBaseUrl() || filled(config($this->baseUrlConfig() ?? '')));
    }

    /** Whether the admin must give its address: each Azure resource has its own. */
    public function needsBaseUrl(): bool
    {
        return $this === self::Azure;
    }

    /** The first connected provider that can do something, in "Automatic" order. */
    public static function firstConnectedFor(AiCapability $capability): ?self
    {
        return array_find(self::cases(), fn (self $provider): bool => $provider->can($capability) && $provider->isConnected());
    }

    /**
     * The chat models the setup picks for the agents, as slots: one slot per
     * model maker, each its ids best first. The first id the provider lists
     * fills a slot ({@see ModelPresets}), so a model it no longer serves is
     * passed over; the first slot gives the default for new agents. A provider
     * with many makers (OpenRouter) gets a few, so agents can try them side by
     * side.
     *
     * The picks answer from the knowledge base well and do not reason
     * (models.dev `reasoning: false`): a reasoning model thinks before every
     * reply, which is slower and costs more, and adds little when the answer
     * is in the retrieved text. Google and Groq serve no such model, so they
     * keep their best reasoning ones.
     *
     * @return list<list<string>>
     */
    public function presetChatModels(): array
    {
        return match ($this) {
            self::Scaleway => [
                ['qwen3-235b-a22b-instruct-2507'],
                ['llama-3.3-70b-instruct'],
                ['mistral-small-3.2-24b-instruct-2506'],
            ],
            self::OpenRouter => [
                ['qwen/qwen3-235b-a22b-2507', 'qwen/qwen3-next-80b-a3b-instruct'],
                ['openai/gpt-4.1', 'openai/gpt-4.1-mini'],
                ['mistralai/mistral-large-2512', 'mistralai/mistral-medium-3.1'],
                ['meta-llama/llama-3.3-70b-instruct', 'meta-llama/llama-4-maverick'],
            ],
            self::OpenAi => [['gpt-4.1', 'gpt-4.1-mini']],
            self::Mistral => [['mistral-large-2512', 'ministral-14b-2512']],
            self::Google => [['gemini-3.8-flash', 'gemini-3.6-flash', 'gemini-2.5-flash']],
            self::Groq => [['openai/gpt-oss-120b', 'llama-3.3-70b-versatile']],
            self::Anthropic => [['claude-sonnet-5-5', 'claude-sonnet-5', 'claude-sonnet-4-6']],
            self::Cohere, self::Jina, self::Voyage, self::Azure => [],
        };
    }

    /**
     * The model the setup picks for quick tasks (guardrails, follow-up
     * questions, titles…), best first: small, fast, cheap and without
     * reasoning where the provider has one. The first one the provider lists
     * is picked.
     *
     * @return list<string>
     */
    public function presetQuickTaskModels(): array
    {
        return match ($this) {
            self::Scaleway => ['mistral-small-3.2-24b-instruct-2506'],
            self::OpenRouter => ['qwen/qwen3-30b-a3b-instruct-2507', 'openai/gpt-4.1-mini', 'mistralai/ministral-8b-2512'],
            self::OpenAi => ['gpt-4.1-mini', 'gpt-4.1-nano'],
            self::Mistral => ['ministral-8b-2512', 'ministral-8b-latest'],
            self::Google => ['gemini-3.5-flash-lite', 'gemini-2.5-flash-lite'],
            self::Groq => ['openai/gpt-oss-20b'],
            self::Anthropic => ['claude-haiku-5-5', 'claude-haiku-4-5'],
            self::Cohere, self::Jina, self::Voyage, self::Azure => [],
        };
    }

    /**
     * The model the setup picks for image descriptions (the pictures inside
     * PDF files and other documents), best first: small models that read
     * images, without reasoning where the provider has one. The first one the
     * provider lists is picked; with none, image descriptions stay off.
     *
     * @return list<string>
     */
    public function presetVisionModels(): array
    {
        return match ($this) {
            self::Scaleway => ['mistral-small-3.2-24b-instruct-2506'],
            self::OpenRouter => ['qwen/qwen3-vl-30b-a3b-instruct', 'mistralai/mistral-small-3.2-24b-instruct', 'openai/gpt-4.1-mini'],
            self::OpenAi => ['gpt-4.1-mini'],
            self::Mistral => ['ministral-14b-2512', 'mistral-large-2512'],
            self::Google => ['gemini-3.5-flash-lite', 'gemini-2.5-flash-lite', 'gemini-2.5-flash'],
            self::Groq => ['meta-llama/llama-4-scout-17b-16e-instruct', 'meta-llama/llama-4-maverick-17b-128e-instruct'],
            self::Anthropic => ['claude-haiku-5-5', 'claude-haiku-4-5'],
            self::Cohere, self::Jina, self::Voyage, self::Azure => [],
        };
    }
}
