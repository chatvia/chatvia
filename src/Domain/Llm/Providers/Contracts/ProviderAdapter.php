<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Contracts;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;

/**
 * Everything the app knows about one kind of LLM provider: how it is shown to
 * an admin, its address and key, the chat client that calls it, and how its
 * model list is read. One class per {@see LlmProviderKind} case
 * ({@see LlmProviderKind::adapter()}); nothing outside an adapter branches on
 * the provider kind.
 */
interface ProviderAdapter
{
    public function kind(): LlmProviderKind;

    public function label(): string;

    /** One sentence for the provider pickers. */
    public function description(): string;

    /** The API address of a provider row without one, or null when the admin must give it. */
    public function defaultBaseUrl(): ?string;

    /** Whether a row must name its own address: a server of your own. */
    public function acceptsBaseUrl(): bool;

    /** Help under the address field, for providers that accept one. */
    public function baseUrlHelp(): ?string;

    /** The provider's API address for a row: its own, its account's, or the default, without a trailing slash. */
    public function baseUrl(LlmProvider $provider): string;

    public function needsApiKey(): bool;

    /** Where an admin creates an API key, if the provider has such a page. */
    public function keysUrl(): ?string;

    /** Whether its chat API reads a whole PDF passed by URL (a `document_url` part). */
    public function readsDocuments(): bool;

    /** The chat client for a provider row of this kind. */
    public function client(LlmProvider $provider): LlmProviderInterface;

    /**
     * The chat models the provider offers, with what it (and the adapter)
     * knows about each. Loading the list also checks the address and key.
     *
     * @return Collection<int, RemoteModelData>
     *
     * @throws ConnectionException|RequestException
     */
    public function models(LlmProvider $provider): Collection;

    /**
     * Whether a failed call's reply means the key was refused: 401 or 403
     * for most providers. Some answer a wrong key otherwise (Gemini: 400).
     */
    public function refusesKey(Response $response): bool;

    /**
     * The AI provider account whose key (and address) rows of this kind call
     * with, set once in Settings → AI providers; null for a server of your
     * own, whose row holds its own.
     */
    public function account(): ?AiProvider;

    /**
     * The key a row calls with: its own (a server of your own, or a key
     * being checked before it is saved), else its account's. Null when
     * there is none.
     */
    public function apiKey(LlmProvider $provider): ?string;

    /** Whether a row can call: it has a key, or its server needs none. */
    public function isConnected(LlmProvider $provider): bool;

    /**
     * An unsaved row of this kind that calls with its account's key and
     * address, for the clients that are not catalog models (Mistral OCR and
     * whole-PDF reading).
     */
    public function standalone(): LlmProvider;
}
