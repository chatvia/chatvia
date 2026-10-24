<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Data\Catalog\ModelFactsData;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Exceptions\NoSummaryModelException;
use Domain\Llm\Models\Llm;

/**
 * The catalog model that reads a whole source as a PDF file, for the
 * summary of each source and its analysis (type, language, likely
 * questions). An admin turns summaries on or off
 * (`chatvia.ai.source_summaries.enabled`) and may pick the model
 * (`chatvia.ai.source_summaries.model`) in Settings → AI models → Documents.
 *
 * The picked model is used while it may be used and reads PDF files; with
 * none picked, the default chat model when it reads them, else the first
 * model that does. With no such model there are no summaries, and the
 * source is still found by its pages.
 *
 * A model counts when its models.dev inputs list `pdf`: every provider's
 * request code sends the PDF in its own format.
 */
final readonly class SourceSummaryModel
{
    public const string ENABLED_KEY = 'chatvia.ai.source_summaries.enabled';

    public const string MODEL_KEY = 'chatvia.ai.source_summaries.model';

    public function __construct(
        private ModelsDevCatalog $catalog,
        private TaskModels $tasks,
    ) {}

    /** Whether the admin allows source summaries on this install. */
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
            return $picked->isAvailable() && $this->readsPdf($picked) ? $picked : null;
        }

        return $this->automatic();
    }

    /** The model used while none is picked. */
    public function automatic(): ?Llm
    {
        $default = $this->tasks->find(LlmTask::Chat);

        if ($default instanceof Llm && $this->readsPdf($default)) {
            return $default;
        }

        return Llm::available()->first(fn (Llm $llm): bool => $this->readsPdf($llm));
    }

    public function isAvailable(): bool
    {
        return $this->find() instanceof Llm;
    }

    /**
     * @throws NoSummaryModelException
     */
    public function model(): Llm
    {
        return $this->find() ?? throw new NoSummaryModelException;
    }

    /**
     * Whether a model reads PDF files: as its own facts say, else as
     * models.dev says now (a model added before its facts kept its inputs).
     */
    public function readsPdf(Llm $llm): bool
    {
        if (($reads = $llm->readsFiles()) !== null) {
            return $reads;
        }

        $entry = $this->catalog->storedEntry($llm->provider->kind, $llm->name);

        return $entry !== null && ModelFactsData::fromCatalog($entry)->readsFiles === true;
    }
}
