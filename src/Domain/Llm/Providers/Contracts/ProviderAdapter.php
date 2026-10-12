<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Contracts;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\LlmProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

/**
 * Everything the app knows about one kind of LLM provider: how it is shown to
 * an admin, its address and key, the chat client that calls it, and how its
 * model list is read. One class per {@see LlmProviderEnum} case
 * ({@see LlmProviderEnum::adapter()}); nothing outside an adapter branches on
 * the provider kind.
 */
interface ProviderAdapter
{
    public function kind(): LlmProviderEnum;

    public function label(): string;

    /** One sentence for the provider pickers. */
    public function description(): string;

    /** The API address of a provider row without one, or null when the admin must give it. */
    public function defaultBaseUrl(): ?string;

    /** Whether an admin may give an address of their own (a self-hosted server, a cloud project). */
    public function acceptsBaseUrl(): bool;

    /** Help under the address field, for providers that accept one. */
    public function baseUrlHelp(): ?string;

    /** The provider's API address for a row: its own, or the default, without a trailing slash. */
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
     * The config keys of the same service in Settings → AI, when it is one
     * (Scaleway's key also runs Qwen3 search and speech to text): `api_key`,
     * and `base_url` when it has an address.
     *
     * @return array{api_key?: string, base_url?: string}
     */
    public function settingsConfig(): array;

    /**
     * That service as an unsaved provider row, from what Settings → AI holds,
     * for the clients that are not catalog providers (Mistral OCR and
     * whole-PDF reading).
     */
    public function fromSettings(): LlmProvider;
}
