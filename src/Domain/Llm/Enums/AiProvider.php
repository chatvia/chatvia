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
    case Cohere = 'cohere';
    case Jina = 'jina';
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
            self::Cohere => [AiCapability::Embeddings, AiCapability::Reranking],
            self::Jina => [AiCapability::Embeddings],
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
            self::Cohere => 'Cohere',
            self::Jina => 'Jina',
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
            self::Cohere => 'Embeddings and reranking.',
            self::Jina => 'Embeddings.',
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
            self::Cohere => 'services.cohere.api_key',
            self::Jina => 'services.jina.api_key',
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
            self::Azure => 'services.azure_document_intelligence.endpoint',
            self::OpenRouter, self::Groq => null,
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
            self::Cohere => 'https://dashboard.cohere.com/api-keys',
            self::Jina => 'https://jina.ai/api-dashboard/key-manager',
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
            self::Cohere, self::Jina, self::Azure => null,
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
            && (! $this->needsBaseUrl() || filled(config((string) $this->baseUrlConfig())));
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
     * passed over; the first slot gives the default for new agents. A provider with many makers (OpenRouter) gets a few,
     * so agents can try them side by side.
     *
     * @return list<list<string>>
     */
    public function presetChatModels(): array
    {
        return match ($this) {
            self::Scaleway => [
                ['gpt-oss-120b', 'llama-3.3-70b-instruct'],
                ['qwen3.5-397b-a17b', 'qwen3-235b-a22b-instruct-2507'],
                ['mistral-medium-3.5-128b'],
            ],
            self::OpenRouter => [
                ['openai/gpt-6.1-sol', 'openai/gpt-6-sol', 'openai/gpt-5.6-terra', 'openai/gpt-5-mini'],
                ['qwen/qwen3.7-plus', 'qwen/qwen3.6-plus', 'qwen/qwen3.5-397b-a17b', 'qwen/qwen3-235b-a22b-2507'],
                ['google/gemini-3.8-flash', 'google/gemini-3.6-flash', 'google/gemini-2.5-flash'],
                ['openai/gpt-oss-120b'],
            ],
            self::OpenAi => [['gpt-6.1-sol', 'gpt-6-sol', 'gpt-5.6-terra', 'gpt-5-mini', 'gpt-4.1-mini']],
            self::Mistral => [['mistral-medium-latest', 'mistral-small-latest']],
            self::Google => [['gemini-3.8-flash', 'gemini-3.6-flash', 'gemini-2.5-flash']],
            self::Groq => [['openai/gpt-oss-120b', 'llama-3.3-70b-versatile']],
            self::Cohere, self::Jina, self::Azure => [],
        };
    }

    /**
     * The model the setup picks for quick tasks (guardrails, follow-up
     * questions, titles…), best first: small, fast and cheap. The first
     * one the provider lists is picked.
     *
     * @return list<string>
     */
    public function presetQuickTaskModels(): array
    {
        return match ($this) {
            self::Scaleway => ['qwen3.6-35b-a3b', 'mistral-small-3.2-24b-instruct-2506', 'gemma-4-26b-a4b-it'],
            self::OpenRouter => ['openai/gpt-6-luna', 'openai/gpt-5.6-luna', 'qwen/qwen3.7-flash', 'openai/gpt-oss-20b'],
            self::OpenAi => ['gpt-6-luna', 'gpt-5.6-luna', 'gpt-5-nano', 'gpt-4.1-nano'],
            self::Mistral => ['mistral-small-latest', 'ministral-8b-latest'],
            self::Google => ['gemini-3.5-flash-lite', 'gemini-2.5-flash-lite'],
            self::Groq => ['openai/gpt-oss-20b'],
            self::Cohere, self::Jina, self::Azure => [],
        };
    }
}
