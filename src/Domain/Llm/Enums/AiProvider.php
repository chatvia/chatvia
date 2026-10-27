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
 * document reading Mistral before Azure, reading aloud ElevenLabs (which
 * reads the text as it is) before OpenAI (whose realtime model talks).
 * Image and video generation have no "Automatic": an admin picks one.
 */
enum AiProvider: string
{
    case Scaleway = 'scaleway';
    case OpenRouter = 'openrouter';
    case ElevenLabs = 'elevenlabs';
    case OpenAi = 'openai';
    case Mistral = 'mistral';
    case Google = 'google';
    case Groq = 'groq';
    case Anthropic = 'anthropic';
    case AzureOpenAi = 'azure_openai';
    case Cohere = 'cohere';
    case Jina = 'jina';
    case Voyage = 'voyage';
    case Azure = 'azure';
    case Kling = 'kling';
    case BytePlus = 'byteplus';
    case Runway = 'runway';
    case Fal = 'fal';

    /**
     * @return list<AiCapability>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::Scaleway => [AiCapability::Chat, AiCapability::Embeddings, AiCapability::Speech],
            self::OpenRouter => [AiCapability::Chat, AiCapability::Embeddings, AiCapability::Reranking, AiCapability::ImageGeneration],
            self::ElevenLabs => [AiCapability::TextToSpeech, AiCapability::AudioGeneration, AiCapability::VideoGeneration],
            self::OpenAi => [AiCapability::Chat, AiCapability::Embeddings, AiCapability::Speech, AiCapability::Voice, AiCapability::TextToSpeech, AiCapability::AudioGeneration, AiCapability::ImageGeneration],
            self::Mistral => [AiCapability::Chat, AiCapability::Embeddings, AiCapability::Documents],
            self::Google => [AiCapability::Chat, AiCapability::Embeddings, AiCapability::ImageGeneration, AiCapability::VideoGeneration],
            self::Groq => [AiCapability::Chat],
            self::Anthropic => [AiCapability::Chat],
            self::AzureOpenAi => [AiCapability::Chat],
            self::Cohere => [AiCapability::Embeddings, AiCapability::Reranking],
            self::Jina => [AiCapability::Embeddings],
            self::Voyage => [AiCapability::Embeddings],
            self::Azure => [AiCapability::Documents],
            self::Kling, self::BytePlus, self::Runway => [AiCapability::VideoGeneration],
            self::Fal => [AiCapability::ImageGeneration, AiCapability::VideoGeneration],
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
            self::ElevenLabs => 'ElevenLabs',
            self::OpenAi => 'OpenAI',
            self::Mistral => 'Mistral',
            self::Google => 'Google Gemini',
            self::Groq => 'Groq',
            self::Anthropic => 'Anthropic',
            self::AzureOpenAi => 'Azure OpenAI',
            self::Cohere => 'Cohere',
            self::Jina => 'Jina',
            self::Voyage => 'Voyage AI',
            self::Azure => 'Azure Document Intelligence',
            self::Kling => 'Kling',
            self::BytePlus => 'BytePlus ModelArk',
            self::Runway => 'Runway',
            self::Fal => 'fal.ai',
        };
    }

    /** One short sentence for the provider pickers. */
    public function description(): string
    {
        return match ($this) {
            self::Scaleway => 'Open models hosted in Europe.',
            self::OpenRouter => 'Hundreds of models from many companies, with one key.',
            self::ElevenLabs => 'Natural voices in many languages, for reading replies aloud, and videos.',
            self::OpenAi => 'The GPT models.',
            self::Mistral => 'Mistral\'s models, and reading of documents and PDFs.',
            self::Google => 'The Gemini models.',
            self::Groq => 'Open models with very fast answers.',
            self::Anthropic => 'The Claude models.',
            self::AzureOpenAi => 'The GPT models and others you deploy in your own Azure resource.',
            self::Cohere => 'Embeddings and reranking.',
            self::Jina => 'Embeddings.',
            self::Voyage => 'Embeddings made for search.',
            self::Azure => 'Reads PDFs, Word, PowerPoint and images, with their tables. Needs the standard tier (S0): the free one reads 2 pages of each file.',
            self::Kling => 'Kling\'s video models.',
            self::BytePlus => 'ByteDance\'s Seedance video models.',
            self::Runway => 'Runway\'s video models, and others such as Veo and Kling.',
            self::Fal => 'Many image and video models, open ones too, with one key.',
        };
    }

    /** The config key of its API key. */
    public function apiKeyConfig(): string
    {
        return match ($this) {
            self::Scaleway => 'services.scalewayai.api_key',
            self::OpenRouter => 'services.openrouter.api_key',
            self::ElevenLabs => 'services.elevenlabs.api_key',
            self::OpenAi => 'services.openai.api_key',
            self::Mistral => 'services.mistral.api_key',
            self::Google => 'services.gemini.api_key',
            self::Groq => 'services.groq.api_key',
            self::Anthropic => 'services.anthropic.api_key',
            self::AzureOpenAi => 'services.azure_openai.api_key',
            self::Cohere => 'services.cohere.api_key',
            self::Jina => 'services.jina.api_key',
            self::Voyage => 'services.voyage.api_key',
            self::Azure => 'services.azure_document_intelligence.key',
            self::Kling => 'services.kling.api_key',
            self::BytePlus => 'services.byteplus.api_key',
            self::Runway => 'services.runway.api_key',
            self::Fal => 'services.fal.api_key',
        };
    }

    /** The config key of its API address, for providers whose address can change. */
    public function baseUrlConfig(): ?string
    {
        return match ($this) {
            self::Scaleway => 'services.scalewayai.endpoint',
            self::ElevenLabs => 'services.elevenlabs.endpoint',
            self::OpenAi => 'services.openai.api_endpoint',
            self::Mistral => 'services.mistral.base_url',
            self::Google => 'services.gemini.api_endpoint',
            self::Cohere => 'services.cohere.api_endpoint',
            self::Jina => 'services.jina.api_endpoint',
            self::Voyage => 'services.voyage.api_endpoint',
            self::Azure => 'services.azure_document_intelligence.endpoint',
            self::AzureOpenAi => 'services.azure_openai.endpoint',
            self::Kling => 'services.kling.endpoint',
            self::BytePlus => 'services.byteplus.endpoint',
            self::OpenRouter, self::Groq, self::Anthropic, self::Runway, self::Fal => null,
        };
    }

    /** Where an admin creates an API key. */
    public function keysUrl(): string
    {
        return match ($this) {
            self::Scaleway => 'https://console.scaleway.com/iam/api-keys',
            self::OpenRouter => 'https://openrouter.ai/settings/keys',
            self::ElevenLabs => 'https://elevenlabs.io/app/settings/api-keys',
            self::OpenAi => 'https://platform.openai.com/api-keys',
            self::Mistral => 'https://console.mistral.ai/api-keys',
            self::Google => 'https://aistudio.google.com/apikey',
            self::Groq => 'https://console.groq.com/keys',
            self::Anthropic => 'https://console.anthropic.com/settings/keys',
            self::AzureOpenAi => 'https://portal.azure.com/#view/Microsoft_Azure_ProjectOxford/CognitiveServicesHub/~/OpenAI',
            self::Cohere => 'https://dashboard.cohere.com/api-keys',
            self::Jina => 'https://jina.ai/api-dashboard/key-manager',
            self::Voyage => 'https://dashboard.voyageai.com/organization/api-keys',
            self::Azure => 'https://portal.azure.com/#view/Microsoft_Azure_ProjectOxford/CognitiveServicesHub/~/DocumentIntelligence',
            self::Kling => 'https://app.klingai.com/global/dev/api-key',
            self::BytePlus => 'https://console.byteplus.com/ark/region:ark+ap-southeast-1/apiKey',
            self::Runway => 'https://dev.runwayml.com/',
            self::Fal => 'https://fal.ai/dashboard/keys',
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
            self::AzureOpenAi => LlmProviderKind::Azure,
            self::ElevenLabs, self::Cohere, self::Jina, self::Voyage, self::Azure, self::Kling, self::BytePlus, self::Runway, self::Fal => null,
        };
    }

    /** The account of a chat provider kind, or null for a server of your own. */
    public static function forChatKind(LlmProviderKind $kind): ?self
    {
        return array_find(self::cases(), fn (self $provider): bool => $provider->chatKind() === $kind);
    }

    /** Whether its API key is set, and its address when it has no default (the Azure ones). */
    public function isConnected(): bool
    {
        return filled(config($this->apiKeyConfig()))
            && (! $this->needsBaseUrl() || filled(config($this->baseUrlConfig() ?? '')));
    }

    /** Whether the admin must give its address: each Azure resource has its own. */
    public function needsBaseUrl(): bool
    {
        return in_array($this, [self::Azure, self::AzureOpenAi], true);
    }

    /** Help under the address field, for the providers that need one. */
    public function baseUrlHelp(): ?string
    {
        return match ($this) {
            self::Azure => 'Your resource\'s endpoint, from the Azure portal, such as https://my-resource.cognitiveservices.azure.com.',
            self::AzureOpenAi => 'Your resource\'s endpoint, from the Azure portal or Microsoft Foundry, such as https://my-resource.openai.azure.com.',
            self::Scaleway, self::OpenRouter, self::ElevenLabs, self::OpenAi, self::Mistral, self::Google, self::Groq,
            self::Anthropic, self::Cohere, self::Jina, self::Voyage, self::Kling, self::BytePlus, self::Runway, self::Fal => null,
        };
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
     * The picks call tools, stay close to the retrieved text (few made-up
     * facts in grounded answers), and answer without thinking: they reason
     * not at all (models.dev `reasoning: false`), or they take the effort
     * `none`, which an agent sends while its reasoning is off. A model that
     * thinks before every reply is slower and costs more, and adds little
     * when the answer is in the retrieved text. Anthropic serves no such
     * model, and Google's (Gemma, Flash-Lite) are made for lighter work, so
     * both keep their best ones at the least thinking they take.
     *
     * @return list<list<string>>
     */
    public function presetChatModels(): array
    {
        return match ($this) {
            self::Scaleway => [
                ['deepseek-v4.1-flash', 'deepseek-v4-flash-0731'],
                ['qwen3.8-27b', 'qwen3-235b-a22b-instruct-2507'],
                ['gemma-4-26b-a4b-it'],
                ['llama-3.3-70b-instruct'],
            ],
            self::OpenRouter => [
                ['deepseek/deepseek-v4.1-flash', 'deepseek/deepseek-v4-flash'],
                ['openai/gpt-6-sol', 'openai/gpt-5.4-mini', 'openai/gpt-4.1'],
                ['google/gemma-4-26b-a4b-it', 'google/gemma-4-31b-it'],
                ['qwen/qwen3.8-27b', 'qwen/qwen3-235b-a22b-2507'],
                ['anthropic/claude-haiku-5.5', 'anthropic/claude-haiku-4.5'],
            ],
            self::OpenAi => [['gpt-6-sol', 'gpt-5.4-mini', 'gpt-4.1']],
            self::Mistral => [['mistral-large-2512', 'ministral-14b-2512']],
            self::Google => [['gemini-3.8-flash', 'gemini-3.6-flash', 'gemini-2.5-flash']],
            self::Groq => [
                ['qwen/qwen3.8-27b', 'qwen/qwen3.6-27b'],
                ['llama-3.3-70b-versatile'],
            ],
            self::Anthropic => [['claude-sonnet-5-5', 'claude-sonnet-5', 'claude-sonnet-4-6']],
            self::ElevenLabs, self::AzureOpenAi, self::Cohere, self::Jina, self::Voyage, self::Azure, self::Kling, self::BytePlus, self::Runway, self::Fal => [],
        };
    }

    /**
     * The model the setup picks for quick tasks (guardrails, follow-up
     * questions, titles…), best first: small, fast, cheap, good at short
     * structured answers, and without thinking where the provider has one.
     * Several run before each reply, so speed counts most. The first one the
     * provider lists is picked.
     *
     * @return list<string>
     */
    public function presetQuickTaskModels(): array
    {
        return match ($this) {
            self::Scaleway => ['gemma-4-26b-a4b-it', 'qwen3.6-35b-a3b'],
            self::OpenRouter => ['openai/gpt-6-luna', 'openai/gpt-5.4-nano', 'google/gemma-4-26b-a4b-it'],
            self::OpenAi => ['gpt-6-luna', 'gpt-5.4-nano', 'gpt-4.1-mini'],
            self::Mistral => ['ministral-8b-2512', 'ministral-8b-latest'],
            self::Google => ['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-2.5-flash-lite'],
            self::Groq => ['openai/gpt-oss-20b'],
            self::Anthropic => ['claude-haiku-5-5', 'claude-haiku-4-5'],
            self::ElevenLabs, self::AzureOpenAi, self::Cohere, self::Jina, self::Voyage, self::Azure, self::Kling, self::BytePlus, self::Runway, self::Fal => [],
        };
    }

    /**
     * The model the setup picks for image descriptions (the pictures inside
     * PDF files and other documents), best first: small models that read
     * images, without thinking where the provider has one. The first one the
     * provider lists is picked; with none, image descriptions stay off.
     *
     * @return list<string>
     */
    public function presetVisionModels(): array
    {
        return match ($this) {
            self::Scaleway => ['gemma-4-26b-a4b-it', 'qwen3.6-35b-a3b'],
            self::OpenRouter => ['qwen/qwen3-vl-30b-a3b-instruct', 'openai/gpt-6-luna', 'google/gemma-4-26b-a4b-it'],
            self::OpenAi => ['gpt-6-luna', 'gpt-5.4-nano', 'gpt-4.1-mini'],
            self::Mistral => ['ministral-14b-2512', 'mistral-large-2512'],
            self::Google => ['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-2.5-flash-lite'],
            self::Groq => ['qwen/qwen3.8-27b', 'qwen/qwen3.6-27b', 'meta-llama/llama-4-scout-17b-16e-instruct'],
            self::Anthropic => ['claude-haiku-5-5', 'claude-haiku-4-5'],
            self::ElevenLabs, self::AzureOpenAi, self::Cohere, self::Jina, self::Voyage, self::Azure, self::Kling, self::BytePlus, self::Runway, self::Fal => [],
        };
    }

    /**
     * The model the setup picks for chat file uploads (a PDF file or an
     * image a chatter attaches), best first: models that read both
     * (models.dev `pdf` and `image` inputs), call tools so the agent's tools
     * still work, and answer as well as the chat models. The first one the
     * provider lists is picked; with none, chat file uploads stay off.
     * Scaleway and Groq serve no model that reads PDF files.
     *
     * @return list<string>
     */
    public function presetFileModels(): array
    {
        return match ($this) {
            self::OpenRouter => ['openai/gpt-6-sol', 'openai/gpt-4.1-mini', 'google/gemini-2.5-flash', 'anthropic/claude-haiku-5.5'],
            self::OpenAi => ['gpt-6-sol', 'gpt-4.1', 'gpt-4.1-mini'],
            self::Mistral => ['mistral-large-2512', 'ministral-14b-2512'],
            self::Google => ['gemini-3.8-flash', 'gemini-3.6-flash', 'gemini-2.5-flash'],
            self::Anthropic => ['claude-sonnet-5-5', 'claude-sonnet-5', 'claude-sonnet-4-6'],
            self::Scaleway, self::ElevenLabs, self::Groq, self::AzureOpenAi, self::Cohere, self::Jina, self::Voyage, self::Azure, self::Kling, self::BytePlus, self::Runway, self::Fal => [],
        };
    }
}
