<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Data\Catalog\ModelFactsData;
use Domain\Llm\Enums\FileInput;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Models\Llm;

/**
 * Chat file uploads, for the whole install: whether chatters may attach a
 * PDF file or an image in a chat, and the catalog model that answers about
 * it when the agent's own model cannot read files (an agent whose model
 * reads both answers itself). An admin turns uploads on or off
 * (`chatvia.ai.chat_files.enabled`); off, no agent can offer them.
 *
 * The model is the one picked in Settings → AI models
 * (`chatvia.ai.chat_files.model`) while it may be used and reads files;
 * with none picked, the default chat model when it reads them, else the
 * first model that does. With no such model, uploads are off too: the
 * upload button is hidden and an upload is refused.
 *
 * A model counts only when its models.dev inputs list both `pdf` and
 * `image`, so a chatter may always attach either: a model sent a file it
 * cannot read answers about nothing.
 */
final readonly class ChatFileModel
{
    public const string ENABLED_KEY = 'chatvia.ai.chat_files.enabled';

    public const string MODEL_KEY = 'chatvia.ai.chat_files.model';

    public function __construct(
        private ModelsDevCatalog $catalog,
        private TaskModels $tasks,
    ) {}

    /** Whether the admin allows chat file uploads on this install. */
    public function isEnabled(): bool
    {
        return (bool) config(self::ENABLED_KEY, true);
    }

    public function find(): ?Llm
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $picked = Llm::fromSetting(config(self::MODEL_KEY));

        if ($picked instanceof Llm) {
            return $picked->isAvailable() && $this->readsFiles($picked) ? $picked : null;
        }

        return $this->automatic();
    }

    /** The model used while none is picked. */
    public function automatic(): ?Llm
    {
        $default = $this->tasks->find(LlmTask::Chat);

        if ($default instanceof Llm && $this->readsFiles($default)) {
            return $default;
        }

        return Llm::available()->first(fn (Llm $llm): bool => $this->readsFiles($llm));
    }

    public function isAvailable(): bool
    {
        return $this->find() instanceof Llm;
    }

    /**
     * The MIME types a chatter may attach now: none while uploads are off.
     *
     * @return list<string>
     */
    public function acceptedMimeTypes(): array
    {
        return $this->isAvailable()
            ? array_merge(...array_map(static fn (FileInput $input): array => $input->mimeTypes(), FileInput::cases()))
            : [];
    }

    /**
     * The file extensions a chatter may attach now: none while uploads are off.
     *
     * @return list<string>
     */
    public function acceptedExtensions(): array
    {
        return $this->isAvailable()
            ? array_merge(...array_map(static fn (FileInput $input): array => $input->extensions(), FileInput::cases()))
            : [];
    }

    /**
     * Whether a model reads both PDF files and images: as its own facts say,
     * else as models.dev says now (a model added before its facts kept its
     * inputs).
     */
    public function readsFiles(Llm $llm): bool
    {
        $facts = $llm->facts();

        if ($facts?->readsFiles === null || $facts->readsImages === null) {
            $entry = $this->catalog->storedEntry($llm->provider->kind, $llm->name);
            $facts = $entry !== null ? ModelFactsData::fromCatalog($entry) : $facts;
        }

        return $facts?->readsFiles === true && $facts->readsImages === true;
    }
}
