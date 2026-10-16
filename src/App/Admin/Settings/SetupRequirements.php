<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;

/**
 * What the app cannot work without, and which of it is still missing: an AI
 * provider for chat, a chat model agents can use, and a connected
 * embedding service. The setup asks for these; skipping it warns with
 * this list, and Settings offers to finish the setup while it is not empty.
 */
final class SetupRequirements
{
    /**
     * What is still missing, in plain words; empty when the app can work.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        return array_values(array_filter([
            $this->chatConnected() ? null : 'an AI provider for chat',
            Llm::available()->isNotEmpty() ? null : 'a chat model for the agents',
            $this->embeddingsConnected() ? null : 'an embedding service',
        ]));
    }

    public function complete(): bool
    {
        return $this->missing() === [];
    }

    /** A provider can chat with its key set, or a server of your own is on. */
    private function chatConnected(): bool
    {
        return AiProvider::firstConnectedFor(AiCapability::Chat) instanceof AiProvider
            || LlmProvider::query()->where('kind', LlmProviderKind::Custom)->where('is_enabled', true)->exists();
    }

    /** The provider of the embedding service in use has its key. */
    private function embeddingsConnected(): bool
    {
        return EmbeddingProvider::configured()?->account()->isConnected() ?? false;
    }
}
